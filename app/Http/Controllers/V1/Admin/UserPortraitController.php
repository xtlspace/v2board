<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AliveIpHistory;
use App\Models\SubLog;
use App\Models\SubRule;
use App\Services\UserDistributionService;
use App\Utils\ConfigCache;
use App\Utils\IPLocation;
use App\Utils\OnlineIp\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserPortraitController extends Controller
{
    private const RISK_FIELDS = [
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

    private const RISK_COUNT_OPS = ['>', '>=', '<', '<=', '='];

    private const RISK_STRING_OPS = ['contains', 'equals', 'starts_with', 'not_contains', 'not_equals'];

    public function fetch(Request $request)
    {
        $current = max(1, (int) $request->input('current', 1));
        $pageSize = (int) $request->input('pageSize', 10);
        if ($pageSize < 10) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sortWhitelist = [
            'id', 'user_id', 'ip', 'ip_country', 'ip_region', 'ip_city',
            'ip_isp', 'user_agent', 'created_at',
        ];
        $sort = in_array($request->input('sort'), $sortWhitelist, true) ? $request->input('sort') : 'created_at';

        $builder = SubLog::orderBy($sort, $sortType);

        $this->applyFilters($request, $builder);

        $total = $builder->count();
        $res = $builder->forPage($current, $pageSize)->get();

        // 补充每个用户在 v2_alive_ip_history 中记录的省份（在线记录）
        $userIds = $res->pluck('user_id')->unique()->values()->all();
        $aliveProvinces = [];
        if (!empty($userIds)) {
            $rows = DB::table('v2_alive_ip_history')
                ->select('user_id', 'province')
                ->selectRaw('MAX(last_seen_at) as last_seen_at')
                ->whereIn('user_id', $userIds)
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->groupBy('user_id', 'province')
                ->orderByDesc('last_seen_at')
                ->get();
            foreach ($rows as $row) {
                $aliveProvinces[$row->user_id][] = $row->province;
            }
        }
        $res->each(function ($item) use ($aliveProvinces) {
            $item->alive_provinces = $aliveProvinces[$item->user_id] ?? [];
            return $item;
        });

        return response(['data' => $res, 'total' => $total]);
    }

    public function deleteRecord(Request $request)
    {
        $type = $request->input('type');
        $id = (int) $request->input('id');
        if ($id <= 0) {
            abort(500, '参数错误');
        }

        if ($type === 'sub') {
            $count = DB::table('v2_sub_log')->where('id', $id)->delete();
        } elseif ($type === 'alive') {
            $count = DB::table('v2_alive_ip_history')->where('id', $id)->delete();
        } else {
            abort(500, '参数错误');
        }

        return response(['data' => true, 'count' => $count]);
    }

    private function normalizeProviderKeys($input): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($input)) {
            $input = [];
        }

        $keys = [];
        foreach ($input as $key) {
            if (!is_string($key)) {
                continue;
            }
            $key = trim($key);
            if ($key === '' || ProviderRegistry::find($key) === null) {
                continue;
            }
            $keys[] = $key;
        }
        $keys = array_values(array_unique($keys));

        return $keys ?: ProviderRegistry::keys();
    }

    public function clearUserRecords(Request $request)
    {
        $userId = (int) $request->input('user_id');
        $type = $request->input('type');
        if ($userId <= 0 || !in_array($type, ['sub', 'alive'], true)) {
            abort(500, '参数错误');
        }

        $table = $type === 'sub' ? 'v2_sub_log' : 'v2_alive_ip_history';
        $count = DB::table($table)->where('user_id', $userId)->delete();

        if ($type === 'alive') {
            foreach (['1h', '1d', '7d', '30d'] as $range) {
                try {
                    Cache::forget('user_portrait_distribution|' . $range);
                } catch (\Throwable $e) {
                }
            }
        }

        return response(['data' => true, 'count' => $count]);
    }

    private function riskRules(): array
    {
        $rules = config('v2board.risk_rules', []);
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            $rules = is_array($decoded) ? $decoded : [];
        }
        return is_array($rules) ? $rules : [];
    }

    private function riskFieldOptions(): array
    {
        $fields = [];
        foreach (self::RISK_FIELDS as $key => $meta) {
            $fields[] = [
                'key' => $key,
                'label' => $meta['label'],
                'type' => $meta['type'],
            ];
        }
        return $fields;
    }

    private function normalizeRiskRules($input): array
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

            $id = trim((string) ($rule['id'] ?? ''));
            if ($id === '' || !preg_match('/^[A-Za-z0-9_\-]{1,32}$/', $id)) {
                $id = 'rule' . ($index + 1);
            }
            if (isset($usedIds[$id])) {
                $id .= '_' . ($index + 1);
            }
            $usedIds[$id] = true;

            $conditions = [];
            foreach (array_slice((array) ($rule['conditions'] ?? []), 0, 10) as $cond) {
                if (!is_array($cond)) {
                    continue;
                }
                $field = (string) ($cond['field'] ?? '');
                if (!isset(self::RISK_FIELDS[$field])) {
                    continue;
                }
                $type = self::RISK_FIELDS[$field]['type'];

                if ($type === 'count') {
                    $op = (string) ($cond['op'] ?? '>=');
                    if (!in_array($op, self::RISK_COUNT_OPS, true)) {
                        $op = '>=';
                    }
                    $value = max(0, min(999, (int) ($cond['value'] ?? 0)));
                } elseif ($type === 'string') {
                    $op = (string) ($cond['op'] ?? 'contains');
                    if (!in_array($op, self::RISK_STRING_OPS, true)) {
                        $op = 'contains';
                    }
                    $value = mb_substr(trim((string) ($cond['value'] ?? '')), 0, 100);
                    if ($value === '') {
                        continue;
                    }
                } else {
                    $op = 'is_true';
                    $value = '';
                }

                $conditions[] = ['field' => $field, 'op' => $op, 'value' => $value];
            }

            if (empty($conditions)) {
                continue;
            }

            $rules[] = [
                'id' => $id,
                'name' => $name,
                'enabled' => !empty($rule['enabled']),
                'color' => $color,
                'match' => $match,
                'conditions' => $conditions,
            ];
        }

        return $rules;
    }

    public function getSettings()
    {
        return response(['data' => [
            'ip_lookup_url' => config('v2board.ip_lookup_url', 'https://iplark.com/'),
            'ip_db_v4_path' => (string) config('v2board.ip_db_v4_path', ''),
            'ip_db_v6_path' => (string) config('v2board.ip_db_v6_path', ''),
            'ip_lookup_source_v4' => config('v2board.ip_lookup_source_v4', 'local'),
            'ip_lookup_source_v6' => config('v2board.ip_lookup_source_v6', 'local'),
            'ip_lookup_providers' => ProviderRegistry::enabledKeys(),
            'ip_providers' => ProviderRegistry::options(),
            'risk_rules' => $this->riskRules(),
            'risk_fields' => $this->riskFieldOptions(),
        ]]);
    }

    public function saveSettings(Request $request)
    {
        $url = trim((string) $request->input('ip_lookup_url', ''));
        if ($url === '') {
            $url = 'https://iplark.com/';
        }
        $probe = str_replace('{ip}', '1.1.1.1', $url);
        if (!preg_match('#^https?://#i', $probe) || !filter_var($probe, FILTER_VALIDATE_URL)) {
            abort(500, 'IP 查询接口格式不正确');
        }

        $v4Path = trim((string) $request->input('ip_db_v4_path', ''));
        $v6Path = trim((string) $request->input('ip_db_v6_path', ''));
        if ($v4Path !== '') {
            $resolved = IPLocation::resolvePath($v4Path);
            if (!is_file($resolved) || !is_readable($resolved)) {
                abort(500, 'IPv4 库文件不存在或不可读：' . $resolved);
            }
        }
        if ($v6Path !== '') {
            $resolved = IPLocation::resolvePath($v6Path);
            if (!is_file($resolved) || !is_readable($resolved)) {
                abort(500, 'IPv6 库文件不存在或不可读：' . $resolved);
            }
        }

        $sourceV4 = $request->input('ip_lookup_source_v4', 'local') === 'online' ? 'online' : 'local';
        $sourceV6 = $request->input('ip_lookup_source_v6', 'local') === 'online' ? 'online' : 'local';
        $providers = $this->normalizeProviderKeys($request->input('ip_lookup_providers', '[]'));

        $config = config('v2board') ?? [];
        $config['ip_lookup_url'] = $url;
        $config['ip_db_v4_path'] = $v4Path;
        $config['ip_db_v6_path'] = $v6Path;
        $config['ip_lookup_source_v4'] = $sourceV4;
        $config['ip_lookup_source_v6'] = $sourceV6;
        $config['ip_lookup_providers'] = $providers;
        $exported = var_export($config, 1);
        if (!ConfigCache::write(base_path() . '/config/v2board.php', "<?php\n return $exported ;")) {
            abort(500, '保存失败');
        }
        config([
            'v2board.ip_lookup_url' => $url,
            'v2board.ip_db_v4_path' => $v4Path,
            'v2board.ip_db_v6_path' => $v6Path,
            'v2board.ip_lookup_source_v4' => $sourceV4,
            'v2board.ip_lookup_source_v6' => $sourceV6,
            'v2board.ip_lookup_providers' => $providers,
        ]);

        // IP 库/接口可能已更换，让在线记录的归属地缓存立即失效
        try {
            UserDistributionService::bumpLocationEpoch();
        } catch (\Throwable $e) {
            Log::warning('user_portrait.saveSettings bump epoch failed: ' . $e->getMessage());
        }

        return response(['data' => true]);
    }

    public function saveRiskRules(Request $request)
    {
        $rules = $this->normalizeRiskRules($request->input('risk_rules', '[]'));

        $config = config('v2board') ?? [];
        $config['risk_rules'] = $rules;
        $exported = var_export($config, 1);
        if (!ConfigCache::write(base_path() . '/config/v2board.php', "<?php\n return $exported ;")) {
            abort(500, '保存失败');
        }
        config(['v2board.risk_rules' => $rules]);

        return response(['data' => true]);
    }

    public function testIpLocation(Request $request)
    {
        $ip = trim((string) $request->input('ip', ''));
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            abort(500, '请输入有效的 IP');
        }

        $v4Path = trim((string) $request->input('ip_db_v4_path', ''));
        $v6Path = trim((string) $request->input('ip_db_v6_path', ''));
        $sourceV4 = $request->input('ip_lookup_source_v4', 'local') === 'online' ? 'online' : 'local';
        $sourceV6 = $request->input('ip_lookup_source_v6', 'local') === 'online' ? 'online' : 'local';
        $providers = $this->normalizeProviderKeys($request->input('ip_lookup_providers', '[]'));

        config([
            'v2board.ip_db_v4_path' => $v4Path,
            'v2board.ip_db_v6_path' => $v6Path,
            'v2board.ip_lookup_source_v4' => $sourceV4,
            'v2board.ip_lookup_source_v6' => $sourceV6,
            'v2board.ip_lookup_providers' => $providers,
        ]);

        try {
            $location = new IPLocation();
            $info = $location->find($ip);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        }
        if ($info === null) {
            abort(500, $location->getLastError() ?: '无解析结果');
        }

        return response(['data' => $info]);
    }

    public function rebuildLocation(Request $request)
    {
        $table = $request->input('table');
        if (!in_array($table, ['sub', 'alive'], true)) {
            abort(500, '参数错误');
        }
        $tableName = $table === 'sub' ? 'v2_sub_log' : 'v2_alive_ip_history';
        $ipVersion = (int) $request->input('ip_version', 4) === 6 ? 6 : 4;
        $lastId = max(0, (int) $request->input('last_id', 0));
        $limit = (int) $request->input('limit', 500);
        if ($limit < 50) {
            $limit = 50;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }
        $source = $ipVersion === 6
            ? config('v2board.ip_lookup_source_v6', 'local')
            : config('v2board.ip_lookup_source_v4', 'local');
        if ($source === 'online' && $limit > 100) {
            $limit = 100;
        }

        $versionFilter = function ($query) use ($ipVersion) {
            if ($ipVersion === 6) {
                $query->whereRaw("INSTR(ip, ':') > 0");
            } else {
                $query->whereRaw("INSTR(ip, ':') = 0");
            }
        };

        try {
            $rows = DB::table($tableName)
                ->where('id', '>', $lastId)
                ->where($versionFilter)
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'ip']);

            try {
                $location = new IPLocation();
            } catch (\Throwable $e) {
                throw new \RuntimeException('IP 库不可用：' . $e->getMessage(), 0, $e);
            }

            $resolved = [];
            foreach ($rows as $row) {
                $ip = (string) $row->ip;
                if (!array_key_exists($ip, $resolved)) {
                    $resolved[$ip] = $ip === '' ? null : $location->find($ip);
                }
            }

            foreach ($rows as $row) {
                $info = $resolved[(string) $row->ip] ?? null;
                if ($table === 'sub') {
                    $update = [
                        'ip_country' => $info && $info['country'] !== '' ? $info['country'] : null,
                        'ip_region' => $info && $info['region'] !== '' ? $info['region'] : null,
                        'ip_city' => $info && $info['city'] !== '' ? $info['city'] : null,
                        'ip_isp' => $info && $info['isp'] !== '' ? $info['isp'] : null,
                    ];
                } else {
                    $region = (string) ($info['region'] ?? '');
                    $province = $region !== '' ? UserDistributionService::normalizeProvince($region) : null;
                    $update = [
                        'country' => $info && $info['country'] !== '' ? $info['country'] : null,
                        'province' => $province ?: ($region !== '' ? $region : null),
                        'city' => $info && $info['city'] !== '' ? $info['city'] : null,
                        'isp' => $info && $info['isp'] !== '' ? $info['isp'] : null,
                        'provider' => $info ? UserDistributionService::classifyProvider((string) $info['isp']) : 'other',
                    ];
                }
                DB::table($tableName)->where('id', $row->id)->update($update);
            }

            $done = count($rows) < $limit;
            $newLastId = count($rows) > 0 ? (int) $rows[count($rows) - 1]->id : $lastId;

            if ($done) {
                try {
                    UserDistributionService::bumpLocationEpoch();
                    foreach (['1h', '1d', '7d', '30d'] as $range) {
                        Cache::forget('user_portrait_distribution|' . $range);
                    }
                } catch (\Throwable $e) {
                    Log::warning('user_portrait.rebuildLocation cache reset failed: ' . $e->getMessage());
                }
            }

            return response(['data' => [
                'last_id' => $newLastId,
                'processed' => count($rows),
                'done' => $done,
                'total' => $lastId === 0 ? DB::table($tableName)->where($versionFilter)->count() : null,
            ]]);
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }
            Log::error('user_portrait.rebuildLocation failed', [
                'table' => $table,
                'last_id' => $lastId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            abort(500, $e->getMessage());
        }
    }

    public function resolveUserIps(Request $request)
    {
        $userId = (int) $request->input('user_id');
        $type = $request->input('type');
        if ($userId <= 0 || !in_array($type, ['sub', 'alive'], true)) {
            abort(500, '参数错误');
        }
        $lastId = max(0, (int) $request->input('last_id', 0));
        $limit = (int) $request->input('limit', 200);
        if ($limit < 20) {
            $limit = 20;
        }
        if ($limit > 500) {
            $limit = 500;
        }
        $source = (string) $request->input('source', 'local');

        $forceSource = 'local';
        $forceProviders = null;
        if ($source !== 'local') {
            if (!preg_match('/^provider:([A-Za-z0-9_\-]+)$/', $source, $matches)
                || ProviderRegistry::find($matches[1]) === null) {
                abort(500, '数据源无效');
            }
            $forceSource = 'online';
            $forceProviders = [$matches[1]];
            if ($limit > 100) {
                $limit = 100;
            }
        }

        $tableName = $type === 'sub' ? 'v2_sub_log' : 'v2_alive_ip_history';

        try {
            $location = (new IPLocation())->withSource($forceSource, $forceProviders);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        }

        $rows = DB::table($tableName)
            ->where('user_id', $userId)
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'ip']);

        // 先全部解析成功，再统一写库；任一失败则不修改数据
        $resolved = [];
        foreach ($rows as $row) {
            $ip = trim((string) $row->ip);
            if ($ip === '') {
                continue;
            }
            if (!array_key_exists($ip, $resolved)) {
                $resolved[$ip] = $location->find($ip);
            }
            if ($resolved[$ip] === null) {
                abort(500, 'IP 解析失败（' . $ip . '）：' . ($location->getLastError() ?: '未知原因'));
            }
        }

        $epoch = UserDistributionService::locationEpoch();
        foreach ($rows as $row) {
            $ip = trim((string) $row->ip);
            if ($ip === '' || !isset($resolved[$ip])) {
                continue;
            }
            $info = $resolved[$ip];
            if ($type === 'sub') {
                $update = [
                    'ip_country' => $info['country'] !== '' ? $info['country'] : null,
                    'ip_region' => $info['region'] !== '' ? $info['region'] : null,
                    'ip_city' => $info['city'] !== '' ? $info['city'] : null,
                    'ip_isp' => $info['isp'] !== '' ? $info['isp'] : null,
                ];
            } else {
                $region = (string) $info['region'];
                $province = $region !== '' ? UserDistributionService::normalizeProvince($region) : null;
                $update = [
                    'country' => $info['country'] !== '' ? $info['country'] : null,
                    'province' => $province ?: ($region !== '' ? $region : null),
                    'city' => $info['city'] !== '' ? $info['city'] : null,
                    'isp' => $info['isp'] !== '' ? $info['isp'] : null,
                    'provider' => UserDistributionService::classifyProvider((string) $info['isp']),
                ];
            }
            DB::table($tableName)->where('id', $row->id)->update($update);

            // 清理该 IP 所在网段的归属地缓存
            try {
                Cache::forget('alive_ip_location2|' . $epoch . '|' . md5(IPLocation::subnetKey($ip)));
            } catch (\Throwable $e) {
            }
            IPLocation::forgetOnlineCache($ip);
            if ($forceProviders !== null) {
                IPLocation::forgetOnlineCache($ip, $forceProviders);
            }
        }

        $done = count($rows) < $limit;
        $newLastId = count($rows) > 0 ? (int) $rows[count($rows) - 1]->id : $lastId;

        if ($done) {
            foreach (['1h', '1d', '7d', '30d'] as $range) {
                try {
                    Cache::forget('user_portrait_distribution|' . $range);
                } catch (\Throwable $e) {
                }
            }
        }

        return response(['data' => [
            'last_id' => $newLastId,
            'processed' => count($rows),
            'done' => $done,
            'total' => $lastId === 0
                ? DB::table($tableName)->where('user_id', $userId)->count()
                : null,
        ]]);
    }

    public function detail(Request $request)
    {
        $userId = (int) $request->input('user_id');
        if ($userId <= 0) {
            abort(500, '参数错误');
        }
        $user = DB::table('v2_user')->where('id', $userId)->first();
        if (!$user) {
            abort(500, '用户不存在');
        }

        $subTotal = DB::table('v2_sub_log')->where('user_id', $userId)->count();
        $aliveTotal = DB::table('v2_alive_ip_history')->where('user_id', $userId)->count();

        $subLogs = DB::table('v2_sub_log')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $aliveIps = DB::table('v2_alive_ip_history')
            ->where('user_id', $userId)
            ->orderByDesc('last_seen_at')
            ->limit(200)
            ->get();

        $planName = null;
        if (!empty($user->plan_id)) {
            $planName = DB::table('v2_plan')->where('id', $user->plan_id)->value('name');
        }

        $regLocation = null;
        $loginLocation = null;
        try {
            $ipLocation = new IPLocation();
            if (!empty($user->reg_ip)) {
                $regLocation = $ipLocation->find($user->reg_ip) ?: null;
            }
            if (!empty($user->last_login_ip)) {
                $loginLocation = $ipLocation->find($user->last_login_ip) ?: null;
            }
        } catch (\Exception $e) {
            // IP 归属地解析失败不影响详情
        }

        return response([
            'data' => [
                'user' => [
                    'id' => (int) $user->id,
                    'email' => $user->email,
                    'created_at' => (int) $user->created_at,
                    'expired_at' => $user->expired_at !== null ? (int) $user->expired_at : null,
                    'banned' => (int) $user->banned,
                    'last_login_at' => $user->last_login_at !== null ? (int) $user->last_login_at : null,
                    'plan_name' => $planName,
                    'reg_ip' => $user->reg_ip,
                    'reg_ip_info' => $regLocation,
                    'login_ip' => $user->last_login_ip,
                    'login_ip_info' => $loginLocation,
                ],
                'sub_logs' => $subLogs,
                'sub_total' => $subTotal,
                'sub_provinces' => DB::table('v2_sub_log')
                    ->where('user_id', $userId)
                    ->whereNotNull('ip_region')
                    ->where('ip_region', '!=', '')
                    ->distinct()
                    ->pluck('ip_region')
                    ->all(),
                'alive_ips' => $aliveIps,
                'alive_total' => $aliveTotal,
                'alive_provinces' => DB::table('v2_alive_ip_history')
                    ->where('user_id', $userId)
                    ->whereNotNull('province')
                    ->where('province', '!=', '')
                    ->distinct()
                    ->pluck('province')
                    ->all(),
            ],
        ]);
    }

    public function getFilterOptions(Request $request)
    {
        $mainlandRegions = SubLog::where('ip_country', '中国')
            ->whereNotNull('ip_region')
            ->where('ip_region', '!=', '')
            ->whereNotIn('ip_region', ['香港', '澳门', '台湾'])
            ->distinct()->orderBy('ip_region')->pluck('ip_region');

        $otherRegions = [];
        $otherCountries = SubLog::whereNotNull('ip_country')
            ->where('ip_country', '!=', '')
            ->where('ip_country', '!=', '中国')
            ->distinct()->orderBy('ip_country')->pluck('ip_country');
        foreach ($otherCountries as $country) {
            $otherRegions[] = [
                'value' => 'country:' . $country,
                'label' => $country,
            ];
        }

        $specialRegions = SubLog::where('ip_country', '中国')
            ->whereIn('ip_region', ['香港', '澳门', '台湾'])
            ->distinct()->pluck('ip_region')->all();
        $specialLabels = [
            '香港' => '中国香港',
            '澳门' => '中国澳门',
            '台湾' => '中国台湾',
        ];
        foreach (['香港', '澳门', '台湾'] as $region) {
            if (in_array($region, $specialRegions, true)) {
                $otherRegions[] = [
                    'value' => 'region:' . $region,
                    'label' => $specialLabels[$region],
                ];
            }
        }

        return response([
            'data' => [
                'mainland_regions' => $mainlandRegions,
                'other_regions' => $otherRegions,
            ]
        ]);
    }

    public function getRules(Request $request)
    {
        $rules = SubRule::orderBy('sort')->orderBy('id')->get();
        return response(['data' => $rules]);
    }

    public function clearLogs(Request $request)
    {
        $builder = SubLog::query();
        $this->applyFilters($request, $builder);
        $count = $builder->delete();
        \Illuminate\Support\Facades\Cache::forget('sub_rules');
        return response(['data' => true, 'count' => $count]);
    }

    public function saveRule(Request $request)
    {
        $ruleId = $request->input('id');
        $data = $request->validate([
            'remark' => 'required|string|max:255',
            'sort' => 'required|integer|min:0',
            'user_id' => 'nullable|string|max:128',
            'user_agent' => 'nullable|string|max:255',
            'ip_country' => 'nullable|string|max:128',
            'ip_region' => 'nullable|string|max:128',
            'ip_city' => 'nullable|string|max:128',
            'ip_isp' => 'nullable|string|max:128',
            'original_host' => 'nullable|string|max:255',
            'replace_host' => 'required|string|max:255',
            'replace_port' => 'nullable|integer',
            'type' => 'required|in:0,1',
            'enable' => 'required|in:0,1',
        ]);

        if ($ruleId) {
            $rule = SubRule::find($ruleId);
            if (!$rule) abort(500, '规则不存在');
            $rule->update($data);
        } else {
            $rule = SubRule::create($data);
        }

        \Illuminate\Support\Facades\Cache::forget('sub_rules');
        return response(['data' => $rule]);
    }

    public function dropRule(Request $request)
    {
        $ruleId = $request->input('id');
        if (!$ruleId) abort(500, '参数错误');
        $rule = SubRule::find($ruleId);
        if (!$rule) abort(500, '规则不存在');
        $rule->delete();
        \Illuminate\Support\Facades\Cache::forget('sub_rules');
        return response(['data' => true]);
    }

    public function riskCheck(Request $request)
    {
        $rules = array_values(array_filter($this->riskRules(), function ($rule) {
            return !empty($rule['enabled']) && !empty($rule['conditions']);
        }));
        if (empty($rules)) {
            return response(['data' => [], 'total' => 0]);
        }

        $ruleIds = array_map(function ($rule) {
            return (string) $rule['id'];
        }, $rules);

        $selected = $request->input('rules', '');
        if (is_string($selected)) {
            $selected = $selected === '' ? [] : explode(',', $selected);
        }
        $selected = array_values(array_intersect($ruleIds, array_map('strval', (array) $selected)));
        if (empty($selected)) {
            $selected = $ruleIds;
        }

        $days = (int) $request->input('days', 30);
        if (!in_array($days, [0, 7, 30, 90], true)) {
            $days = 30;
        }

        $sortWhitelist = ['risk_count', 'sub_region_count', 'ua_count', 'alive_province_count', 'last_at'];
        $sort = in_array($request->input('sort'), $sortWhitelist, true) ? $request->input('sort') : 'risk_count';
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $current = max(1, (int) $request->input('current', 1));
        $pageSize = (int) $request->input('pageSize', 10);
        if ($pageSize < 10) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $cacheKey = 'sub_risk|' . md5(serialize([$rules, $selected, $days]));

        $filtered = Cache::remember($cacheKey, 60, function () use ($rules, $selected, $days) {
            return $this->buildRiskRows($rules, $selected, $days);
        });

        usort($filtered, function ($a, $b) use ($sort, $sortType) {
            $left = $a[$sort] ?? 0;
            $right = $b[$sort] ?? 0;
            if ($left == $right) {
                return $a['user_id'] <=> $b['user_id'];
            }
            return $sortType === 'ASC' ? ($left <=> $right) : ($right <=> $left);
        });

        $total = count($filtered);
        $data = array_slice($filtered, ($current - 1) * $pageSize, $pageSize);

        return response(['data' => $data, 'total' => $total]);
    }

    private function buildRiskRows(array $rules, array $selected, int $days): array
    {
        $since = $days > 0 ? time() - $days * 86400 : 0;
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
            ->groupBy('user_id')
            ->get();

        $aliveRows = DB::table('v2_alive_ip_history')
            ->select('user_id')
            ->selectRaw("GROUP_CONCAT(DISTINCT province SEPARATOR ',') AS regions")
            ->selectRaw("GROUP_CONCAT(DISTINCT country SEPARATOR ',') AS countries")
            ->selectRaw("GROUP_CONCAT(DISTINCT isp SEPARATOR ',') AS isps")
            ->selectRaw('MAX(last_seen_at) AS last_seen_at')
            ->when($since > 0, function ($query) use ($since) {
                $query->where('last_seen_at', '>=', $since);
            })
            ->groupBy('user_id')
            ->get();

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
            foreach ($rule['conditions'] as $cond) {
                if ($cond['field'] !== 'sub_user_agent') {
                    continue;
                }
                $key = $cond['op'] . '|' . $cond['value'];
                if (!array_key_exists($key, $uaSets)) {
                    $uaSets[$key] = $this->uaMatchSet($cond, $since);
                }
            }
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

    private function ruleMatches(array $rule, array $user, array $uaSets): bool
    {
        $matchAll = ($rule['match'] ?? 'all') !== 'any';
        foreach ($rule['conditions'] as $cond) {
            $ok = $this->conditionMatches($cond, $user, $uaSets);
            if ($matchAll && !$ok) {
                return false;
            }
            if (!$matchAll && $ok) {
                return true;
            }
        }
        return $matchAll;
    }

    private function conditionMatches(array $cond, array $user, array $uaSets): bool
    {
        $field = (string) $cond['field'];
        $value = $cond['value'];

        switch ($field) {
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

    private function uaMatchSet(array $cond, int $since): array
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
        $result = [];
        foreach ($all->pluck('user_id') as $id) {
            $id = (int) $id;
            if (!isset($matched[$id])) {
                $result[$id] = true;
            }
        }

        return $result;
    }

    public function distribution(Request $request)
    {
        $rangeMap = [
            '1h' => 3600,
            '1d' => 86400,
            '7d' => 604800,
            '30d' => 2592000,
        ];
        $range = $request->input('range', '1h');
        if (!isset($rangeMap[$range])) {
            $range = '1h';
        }
        $seconds = $rangeMap[$range];
        $cacheKey = 'user_portrait_distribution|' . $range;

        $data = Cache::remember($cacheKey, 60, function () use ($range, $seconds) {
            $since = time() - $seconds;
            $query = function () use ($since) {
                return DB::table('v2_alive_ip_history')
                    ->where('last_seen_at', '>=', $since)
                    ->where('country', 'like', '%中国%')
                    ->whereNotNull('province')
                    ->where('province', '!=', '')
                    ->whereNotIn('province', ['香港', '澳门', '台湾']);
            };

            $totalRow = $query()
                ->selectRaw('COUNT(DISTINCT user_id) as total')
                ->first();
            $total = (int) ($totalRow->total ?? 0);

            $provinceRows = $query()
                ->select('province')
                ->selectRaw('COUNT(DISTINCT user_id) as total')
                ->groupBy('province')
                ->get();

            $provinceProviderRows = $query()
                ->select('province', 'provider')
                ->selectRaw('COUNT(DISTINCT user_id) as total')
                ->groupBy('province', 'provider')
                ->get();

            $providerRows = $query()
                ->select('provider')
                ->selectRaw('COUNT(DISTINCT user_id) as total')
                ->groupBy('provider')
                ->get();

            $providers = [
                'telecom' => 0,
                'unicom' => 0,
                'mobile' => 0,
                'cable' => 0,
                'edu' => 0,
                'other' => 0,
            ];
            foreach ($providerRows as $row) {
                $provider = $row->provider ?: 'other';
                if (!array_key_exists($provider, $providers)) {
                    $providers[$provider] = 0;
                }
                $providers[$provider] = (int) $row->total;
            }

            $regions = [];
            foreach ($provinceRows as $row) {
                $regions[$row->province] = [
                    'name' => $row->province,
                    'total' => (int) $row->total,
                    'telecom' => 0,
                    'unicom' => 0,
                    'mobile' => 0,
                    'cable' => 0,
                    'edu' => 0,
                    'other' => 0,
                ];
            }
            foreach ($provinceProviderRows as $row) {
                $province = $row->province;
                if (!isset($regions[$province])) {
                    $regions[$province] = [
                        'name' => $province,
                        'total' => 0,
                        'telecom' => 0,
                        'unicom' => 0,
                        'mobile' => 0,
                        'cable' => 0,
                        'edu' => 0,
                        'other' => 0,
                    ];
                }
                $provider = $row->provider ?: 'other';
                if (!array_key_exists($provider, $regions[$province])) {
                    $regions[$province][$provider] = 0;
                }
                $regions[$province][$provider] = (int) $row->total;
            }

            $regions = array_values($regions);
            usort($regions, function ($a, $b) {
                return $b['total'] <=> $a['total'];
            });

            return [
                'range' => $range,
                'total' => $total,
                'providers' => $providers,
                'regions' => $regions,
            ];
        });

        return response(['data' => $data]);
    }

    public function distributionClear(Request $request)
    {
        $days = (int) $request->input('days', 30);
        if ($days < 1) {
            $days = 30;
        }
        if ($days > 3650) {
            $days = 3650;
        }
        $before = time() - $days * 86400;
        $count = AliveIpHistory::where('last_seen_at', '<', $before)->delete();
        foreach (['1h', '1d', '7d', '30d'] as $range) {
            Cache::forget('user_portrait_distribution|' . $range);
        }

        return response([
            'data' => true,
            'count' => $count,
        ]);
    }

    private function applyFilters(Request $request, $builder)
    {
        if ($request->filled('date_range')) {
            $range = $request->input('date_range');
            if ($range === 'today') {
                $builder->where('created_at', '>=', strtotime('today'));
            } elseif ($range === '7d') {
                $builder->where('created_at', '>=', strtotime('-7 days'));
            } elseif ($range === '30d') {
                $builder->where('created_at', '>=', strtotime('-30 days'));
            }
        }
        if ($request->filled('user_id')) {
            $userId = $request->input('user_id');
            if (preg_match('/^(>=|<=|>|<)(\d+)$/', $userId, $m)) {
                $builder->where('user_id', $m[1], (int)$m[2]);
            } else {
                $builder->where('user_id', (int)$userId);
            }
        }
        if ($request->filled('user_agent')) {
            $keyword = $request->input('user_agent');
            if (str_starts_with($keyword, 'FULL:')) {
                $keyword = mb_substr($keyword, strlen('FULL:'));
                $builder->where('user_agent', $keyword);
            } else {
                $keyword = mb_strtolower($keyword);
                $builder->whereRaw('LOWER(user_agent) LIKE ?', ['%' . $keyword . '%']);
            }
        }
        if ($request->filled('ip')) {
            $builder->where('ip', 'like', '%' . $request->input('ip') . '%');
        }
        $countryGroup = $request->input('country_group');
        $countryGroup = is_string($countryGroup) ? $countryGroup : null;
        $area = $request->input('area', '');
        $area = is_string($area) ? $area : '';
        if ($countryGroup === 'mainland') {
            $builder->where('ip_country', '中国')
                ->whereNotNull('ip_region')
                ->where('ip_region', '!=', '')
                ->whereNotIn('ip_region', ['香港', '澳门', '台湾']);
            if ($area !== '') {
                $builder->where('ip_region', $area);
            }
        } elseif ($countryGroup === 'other') {
            $builder->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('ip_country')
                        ->where('ip_country', '!=', '')
                        ->where('ip_country', '!=', '中国');
                })->orWhere(function ($q) {
                    $q->where('ip_country', '中国')
                        ->whereIn('ip_region', ['香港', '澳门', '台湾']);
                });
            });
            if ($area !== '') {
                if (str_starts_with($area, 'region:')) {
                    $builder->where('ip_country', '中国')
                        ->where('ip_region', substr($area, 7));
                } else {
                    $country = str_starts_with($area, 'country:') ? substr($area, 8) : $area;
                    $builder->where('ip_country', $country);
                }
            }
        } else {
            // 兼容旧 URL 中的 ip_country / ip_region 参数
            if ($request->filled('ip_country')) {
                $builder->where('ip_country', $request->input('ip_country'));
            }
            if ($request->filled('ip_region')) {
                $builder->where('ip_region', $request->input('ip_region'));
            }
        }
        if ($request->filled('ip_city')) {
            $builder->where('ip_city', 'like', '%' . $request->input('ip_city') . '%');
        }
        if ($request->filled('ip_isp')) {
            $builder->where('ip_isp', 'like', '%' . $request->input('ip_isp') . '%');
        }
    }
}
