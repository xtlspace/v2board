<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RiskService
{
    public const FIELDS = [
        'user_id' => ['type' => 'number', 'label' => '用户 ID'],
        'sub_province_count' => ['type' => 'count', 'label' => '订阅省份种类'],
        'alive_province_count' => ['type' => 'count', 'label' => '在线省份种类'],
        'sub_isp_count' => ['type' => 'count', 'label' => '订阅运营商种类'],
        'alive_isp_count' => ['type' => 'count', 'label' => '在线运营商种类'],
        'sub_ua_count' => ['type' => 'count', 'label' => '订阅 UA 种类'],
        'sub_country' => ['type' => 'string', 'label' => '订阅国家'],
        'sub_region' => ['type' => 'string', 'label' => '订阅地区'],
        'sub_isp' => ['type' => 'string', 'label' => '订阅运营商'],
        'sub_user_agent' => ['type' => 'string', 'label' => '订阅 User-Agent'],
        'alive_country' => ['type' => 'string', 'label' => '在线国家'],
        'alive_region' => ['type' => 'string', 'label' => '在线地区'],
        'alive_isp' => ['type' => 'string', 'label' => '在线运营商'],
        'province_mismatch' => ['type' => 'bool', 'label' => '省份不一致'],
    ];

    public const COUNT_OPS = ['>', '>=', '<', '<=', '='];

    public const STRING_OPS = ['contains', 'equals', 'starts_with', 'not_contains', 'not_equals'];

    public function fieldOptions(): array
    {
        $fields = [];
        foreach (self::FIELDS as $key => $meta) {
            $fields[] = [
                'key' => $key,
                'label' => $meta['label'],
                'type' => $meta['type'],
            ];
        }
        return $fields;
    }

    public function normalizeRules($input): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($input)) {
            $input = [];
        }

        $rules = [];
        $usedIds = [];
        foreach (array_slice($input, 0, 20) as $index => $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $name = mb_substr(trim((string) ($rule['name'] ?? '')), 0, 20);
            if ($name === '') {
                continue;
            }

            $color = in_array($rule['color'] ?? '', ['red', 'orange', 'yellow'], true) ? $rule['color'] : 'red';
            $match = ($rule['match'] ?? 'all') === 'any' ? 'any' : 'all';
            $notify = !isset($rule['notify']) || !empty($rule['notify']);

            $id = trim((string) ($rule['id'] ?? ''));
            if ($id === '' || !preg_match('/^[A-Za-z0-9_\-]{1,32}$/', $id)) {
                $id = 'rule' . ($index + 1);
            }
            if (isset($usedIds[$id])) {
                $id .= '_' . ($index + 1);
            }
            $usedIds[$id] = true;

            $rawGroups = $rule['groups'] ?? null;
            if (!is_array($rawGroups) || empty($rawGroups)) {
                $rawGroups = [['match' => $match, 'conditions' => (array) ($rule['conditions'] ?? [])]];
            }

            $groups = [];
            $totalConditions = 0;
            foreach (array_slice($rawGroups, 0, 5) as $group) {
                if ($totalConditions >= 30) {
                    break;
                }
                if (!is_array($group)) {
                    continue;
                }
                $groupMatch = ($group['match'] ?? 'all') === 'any' ? 'any' : 'all';
                $conditions = [];
                foreach (array_slice((array) ($group['conditions'] ?? []), 0, 10) as $cond) {
                    if ($totalConditions + count($conditions) >= 30) {
                        break;
                    }
                    $normalized = $this->normalizeCondition($cond);
                    if ($normalized !== null) {
                        $conditions[] = $normalized;
                    }
                }
                if (empty($conditions)) {
                    continue;
                }
                $totalConditions += count($conditions);
                $groups[] = ['match' => $groupMatch, 'conditions' => $conditions];
            }

            if (empty($groups)) {
                continue;
            }

            $rules[] = [
                'id' => $id,
                'name' => $name,
                'enabled' => !empty($rule['enabled']),
                'notify' => $notify,
                'color' => $color,
                'match' => $match,
                'groups' => $groups,
            ];
        }

        return $rules;
    }

    private function normalizeCondition($cond): ?array
    {
        if (!is_array($cond)) {
            return null;
        }
        $field = (string) ($cond['field'] ?? '');
        if (!isset(self::FIELDS[$field])) {
            return null;
        }
        $type = self::FIELDS[$field]['type'];

        if ($type === 'count') {
            $op = (string) ($cond['op'] ?? '>=');
            if (!in_array($op, self::COUNT_OPS, true)) {
                $op = '>=';
            }
            $value = max(0, min(999, (int) ($cond['value'] ?? 0)));
        } elseif ($type === 'number') {
            $op = (string) ($cond['op'] ?? '>=');
            if (!in_array($op, self::COUNT_OPS, true)) {
                $op = '>=';
            }
            $value = max(0, (int) ($cond['value'] ?? 0));
        } elseif ($type === 'string') {
            $op = (string) ($cond['op'] ?? 'contains');
            if (!in_array($op, self::STRING_OPS, true)) {
                $op = 'contains';
            }
            $value = mb_substr(trim((string) ($cond['value'] ?? '')), 0, 100);
            if ($value === '') {
                return null;
            }
        } else {
            $op = 'is_true';
            $value = '';
        }

        return ['field' => $field, 'op' => $op, 'value' => $value];
    }

    /**
     * 汇总用户风险。
     *
     * @param array $rules     已规范化的规则
     * @param array $selected  需要参与判断的规则 ID
     * @param int   $days      取数天数，0 表示全部历史
     * @param array $userIds   限定用户，空数组表示全部用户
     */
    public function buildRows(array $rules, array $selected, int $days = 0, array $userIds = []): array
    {
        $since = $days > 0 ? time() - $days * 86400 : 0;
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        try {
            DB::statement('SET SESSION group_concat_max_len = 16384');
        } catch (\Throwable $e) {
        }

        $subRows = DB::table('v2_sub_log')
            ->select('user_id')
            ->selectRaw("GROUP_CONCAT(DISTINCT ip_region SEPARATOR ',') AS regions")
            ->selectRaw("GROUP_CONCAT(DISTINCT ip_country SEPARATOR ',') AS countries")
            ->selectRaw("GROUP_CONCAT(DISTINCT ip_isp SEPARATOR ',') AS isps")
            ->selectRaw('COUNT(DISTINCT user_agent) AS ua_count')
            ->selectRaw('MAX(created_at) AS last_at')
            ->when($since > 0, function ($query) use ($since) {
                $query->where('created_at', '>=', $since);
            })
            ->when(!empty($userIds), function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds);
            })
            ->groupBy('user_id')
            ->get();

        $aliveQuery = function (bool $excludeNodeIp) use ($since, $userIds) {
            return DB::table('v2_alive_ip_history')
                ->select('user_id')
                ->selectRaw("GROUP_CONCAT(DISTINCT province SEPARATOR ',') AS regions")
                ->selectRaw("GROUP_CONCAT(DISTINCT country SEPARATOR ',') AS countries")
                ->selectRaw("GROUP_CONCAT(DISTINCT isp SEPARATOR ',') AS isps")
                ->selectRaw('MAX(last_seen_at) AS last_seen_at')
                ->when($since > 0, function ($query) use ($since) {
                    $query->where('last_seen_at', '>=', $since);
                })
                ->when(!empty($userIds), function ($query) use ($userIds) {
                    $query->whereIn('user_id', $userIds);
                })
                ->where('provider', '!=', 'lan')
                ->when($excludeNodeIp, function ($query) {
                    $query->whereNull('alive_ip_node_id');
                })
                ->groupBy('user_id')
                ->get();
        };

        try {
            $aliveRows = $aliveQuery(true);
        } catch (\Throwable $e) {
            // 未执行数据库迁移时降级为不过滤
            $aliveRows = $aliveQuery(false);
        }

        $blank = [
            'sub_regions_raw' => [],
            'sub_countries' => [],
            'sub_isps' => [],
            'ua_count' => 0,
            'alive_regions_raw' => [],
            'alive_countries' => [],
            'alive_isps' => [],
            'last_at' => 0,
            'last_seen_at' => 0,
        ];

        $users = [];

        foreach ($aliveRows as $row) {
            $id = (int) $row->user_id;
            $users[$id] = array_merge($blank, [
                'user_id' => $id,
                'alive_regions_raw' => $this->splitList($row->regions),
                'alive_countries' => $this->splitList($row->countries),
                'alive_isps' => $this->splitList($row->isps),
                'last_seen_at' => (int) $row->last_seen_at,
            ]);
        }

        foreach ($subRows as $row) {
            $id = (int) $row->user_id;
            if (!isset($users[$id])) {
                $users[$id] = array_merge($blank, ['user_id' => $id]);
            }
            $users[$id]['sub_regions_raw'] = $this->splitList($row->regions);
            $users[$id]['sub_countries'] = $this->splitList($row->countries);
            $users[$id]['sub_isps'] = $this->splitList($row->isps);
            $users[$id]['ua_count'] = (int) $row->ua_count;
            $users[$id]['last_at'] = (int) $row->last_at;
        }

        foreach ($users as &$user) {
            $user['sub_regions'] = $this->mainlandProvinces($user['sub_regions_raw']);
            $user['alive_regions'] = $this->mainlandProvinces($user['alive_regions_raw']);
            $user['sub_region_count'] = count($user['sub_regions']);
            $user['alive_province_count'] = count($user['alive_regions']);
            $user['sub_isp_count'] = count($this->ispCategories($user['sub_isps']));
            $user['alive_isp_count'] = count($this->ispCategories($user['alive_isps']));
            $user['province_mismatch'] = !empty($user['sub_regions']) && !empty($user['alive_regions'])
                && empty(array_intersect($user['sub_regions'], $user['alive_regions']));
        }
        unset($user);

        $uaSets = [];
        foreach ($rules as $rule) {
            if (!in_array((string) $rule['id'], $selected, true)) {
                continue;
            }
            foreach ($this->ruleConditions($rule) as $cond) {
                if ($cond['field'] !== 'sub_user_agent') {
                    continue;
                }
                $key = $cond['op'] . '|' . $cond['value'];
                if (!array_key_exists($key, $uaSets)) {
                    $uaSets[$key] = $this->uaMatchSet($cond, $since, $userIds);
                }
            }
        }

        $ignored = [];
        try {
            $ignoredRows = DB::table('v2_user')
                ->where('risk_ignore', 1)
                ->pluck('id');
            foreach ($ignoredRows as $ignoredId) {
                $ignored[(int) $ignoredId] = true;
            }
        } catch (\Throwable $e) {
            $ignored = [];
        }

        $list = [];
        foreach ($users as $user) {
            $tags = [];
            foreach ($rules as $rule) {
                if (!in_array((string) $rule['id'], $selected, true)) {
                    continue;
                }
                if ($this->ruleMatches($rule, $user, $uaSets)) {
                    $tags[] = (string) $rule['id'];
                }
            }
            if (empty($tags)) {
                continue;
            }
            $list[] = [
                'user_id' => $user['user_id'],
                'ignored' => isset($ignored[$user['user_id']]),
                'risk_tags' => $tags,
                'risk_count' => count($tags),
                'sub_regions' => $user['sub_regions'],
                'sub_region_count' => $user['sub_region_count'],
                'ua_count' => $user['ua_count'],
                'alive_provinces' => $user['alive_regions'],
                'alive_province_count' => $user['alive_province_count'],
                'last_at' => max($user['last_at'], $user['last_seen_at']),
            ];
        }

        return $list;
    }

    private function splitList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $items = [];
        foreach (explode(',', (string) $value) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $items[$item] = true;
            }
        }
        return array_keys($items);
    }

    private function mainlandProvinces(array $regions): array
    {
        $provinces = [];
        foreach ($regions as $region) {
            $province = UserDistributionService::normalizeProvince((string) $region);
            if ($province !== null) {
                $provinces[$province] = true;
            }
        }
        return array_keys($provinces);
    }

    private function ispCategories(array $isps): array
    {
        $categories = [];
        foreach ($isps as $isp) {
            $categories[UserDistributionService::classifyProvider((string) $isp)] = true;
        }
        return array_keys($categories);
    }

    public function ruleMatches(array $rule, array $user, array $uaSets): bool
    {
        $groups = $rule['groups'] ?? [];
        if (!is_array($groups) || empty($groups)) {
            $groups = [['match' => $rule['match'] ?? 'all', 'conditions' => $rule['conditions'] ?? []]];
        }

        $matchAll = ($rule['match'] ?? 'all') !== 'any';
        $evaluated = false;

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $conditions = (array) ($group['conditions'] ?? []);
            if (empty($conditions)) {
                continue;
            }
            $evaluated = true;

            $groupAll = ($group['match'] ?? 'all') !== 'any';
            $groupOk = $groupAll;
            foreach ($conditions as $cond) {
                $ok = $this->conditionMatches($cond, $user, $uaSets);
                if ($groupAll && !$ok) {
                    $groupOk = false;
                    break;
                }
                if (!$groupAll && $ok) {
                    $groupOk = true;
                    break;
                }
            }

            if ($matchAll && !$groupOk) {
                return false;
            }
            if (!$matchAll && $groupOk) {
                return true;
            }
        }

        return $evaluated && $matchAll;
    }

    public function ruleConditions(array $rule): array
    {
        $conditions = [];
        $groups = $rule['groups'] ?? [];
        if (is_array($groups) && !empty($groups)) {
            foreach ($groups as $group) {
                if (!is_array($group)) {
                    continue;
                }
                foreach ((array) ($group['conditions'] ?? []) as $cond) {
                    $conditions[] = $cond;
                }
            }
            return $conditions;
        }
        return (array) ($rule['conditions'] ?? []);
    }

    private function conditionMatches(array $cond, array $user, array $uaSets): bool
    {
        $field = (string) $cond['field'];
        $value = $cond['value'];

        switch ($field) {
            case 'user_id':
                return $this->compareNumber((int) $user['user_id'], (string) $cond['op'], (int) $value);
            case 'sub_province_count':
                return $this->compareNumber($user['sub_region_count'], (string) $cond['op'], (int) $value);
            case 'alive_province_count':
                return $this->compareNumber($user['alive_province_count'], (string) $cond['op'], (int) $value);
            case 'sub_isp_count':
                return $this->compareNumber($user['sub_isp_count'], (string) $cond['op'], (int) $value);
            case 'alive_isp_count':
                return $this->compareNumber($user['alive_isp_count'], (string) $cond['op'], (int) $value);
            case 'sub_ua_count':
                return $this->compareNumber($user['ua_count'], (string) $cond['op'], (int) $value);
            case 'province_mismatch':
                return !empty($user['province_mismatch']);
            case 'sub_country':
                return $this->stringSetMatches($user['sub_countries'], $cond);
            case 'sub_region':
                return $this->stringSetMatches($user['sub_regions_raw'], $cond);
            case 'sub_isp':
                return $this->stringSetMatches($user['sub_isps'], $cond);
            case 'sub_user_agent':
                $key = $cond['op'] . '|' . $cond['value'];
                return isset($uaSets[$key][$user['user_id']]);
            case 'alive_country':
                return $this->stringSetMatches($user['alive_countries'], $cond);
            case 'alive_region':
                return $this->stringSetMatches($user['alive_regions_raw'], $cond);
            case 'alive_isp':
                return $this->stringSetMatches($user['alive_isps'], $cond);
        }

        return false;
    }

    private function compareNumber(int $left, string $op, int $right): bool
    {
        switch ($op) {
            case '>':
                return $left > $right;
            case '>=':
                return $left >= $right;
            case '<':
                return $left < $right;
            case '<=':
                return $left <= $right;
            default:
                return $left === $right;
        }
    }

    private function stringSetMatches(array $values, array $cond): bool
    {
        $op = (string) $cond['op'];
        $needle = (string) $cond['value'];
        $negative = in_array($op, ['not_contains', 'not_equals'], true);
        $baseOp = $op === 'not_contains' ? 'contains' : ($op === 'not_equals' ? 'equals' : $op);

        $matched = false;
        foreach ($values as $value) {
            if ($this->stringMatches((string) $value, $baseOp, $needle)) {
                $matched = true;
                break;
            }
        }

        return $negative ? (!empty($values) && !$matched) : $matched;
    }

    private function stringMatches(string $value, string $op, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }
        switch ($op) {
            case 'equals':
                return mb_strtolower($value) === mb_strtolower($needle);
            case 'starts_with':
                return mb_stripos($value, $needle) === 0;
            case 'contains':
            default:
                return mb_stripos($value, $needle) !== false;
        }
    }

    private function uaMatchSet(array $cond, int $since, array $userIds = []): array
    {
        $op = (string) $cond['op'];
        $needle = (string) $cond['value'];
        $negative = in_array($op, ['not_contains', 'not_equals'], true);
        $baseOp = $op === 'not_contains' ? 'contains' : ($op === 'not_equals' ? 'equals' : $op);
        $escaped = addcslashes($needle, '%_\\');

        $query = DB::table('v2_sub_log')->select('user_id')->distinct();
        if ($since > 0) {
            $query->where('created_at', '>=', $since);
        }
        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }
        if ($baseOp === 'equals') {
            $query->where('user_agent', $needle);
        } elseif ($baseOp === 'starts_with') {
            $query->where('user_agent', 'like', $escaped . '%');
        } else {
            $query->where('user_agent', 'like', '%' . $escaped . '%');
        }

        $matched = [];
        foreach ($query->pluck('user_id') as $id) {
            $matched[(int) $id] = true;
        }

        if (!$negative) {
            return $matched;
        }

        $all = DB::table('v2_sub_log')->select('user_id')->distinct();
        if ($since > 0) {
            $all->where('created_at', '>=', $since);
        }
        if (!empty($userIds)) {
            $all->whereIn('user_id', $userIds);
        }
        $result = [];
        foreach ($all->pluck('user_id') as $id) {
            $id = (int) $id;
            if (!isset($matched[$id])) {
                $result[$id] = true;
            }
        }

        return $result;
    }
}
