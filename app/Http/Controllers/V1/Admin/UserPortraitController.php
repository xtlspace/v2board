<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AliveIpHistory;
use App\Models\SubLog;
use App\Models\SubRule;
use App\Services\NodeIpRegistry;
use App\Services\RiskService;
use App\Services\SubRuleMatcher;
use App\Services\UserDistributionService;
use App\Utils\ConfigCache;
use App\Utils\IPLocation;
use App\Utils\IpNormalizer;
use App\Utils\OnlineIp\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UserPortraitController extends Controller
{
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
        return app(RiskService::class)->normalizeRules(config('v2board.risk_rules', []));
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
            'risk_fields' => app(RiskService::class)->fieldOptions(),
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
        unset($config['risk_notify']);
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
        $service = app(RiskService::class);
        $rules = $service->normalizeRules($request->input('risk_rules', '[]'));

        $config = config('v2board') ?? [];
        $config['risk_rules'] = $rules;
        unset($config['risk_notify']);
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

        // 与用户管理设备数使用同一份缓存判断实时在线
        $aliveCache = Cache::get('ALIVE_IP_USER_' . $userId, []);
        $chainForwardIgnore = (int) config('v2board.device_limit_chain_forward', 0) === 1;
        $ignoredNodeIps = [];
        if ($chainForwardIgnore) {
            $ignoredNodeIps = (new NodeIpRegistry())->packedIpSet();
        }

        $onlineIps = [];
        if (is_array($aliveCache)) {
            foreach ($aliveCache as $nodeKey => $nodeData) {
                if ($nodeKey === 'alive_ip' || !is_array($nodeData) || empty($nodeData['aliveips']) || !is_array($nodeData['aliveips'])) {
                    continue;
                }
                foreach ($nodeData['aliveips'] as $ipNodeId) {
                    $ip = explode('_', (string) $ipNodeId)[0];
                    $packed = $this->normalizeIpForCompare($ip);
                    if ($packed === null) {
                        continue;
                    }
                    if ($chainForwardIgnore && isset($ignoredNodeIps[$packed])) {
                        continue;
                    }
                    $onlineIps[$packed] = true;
                }
            }
        }
        foreach ($aliveIps as $row) {
            $packed = $this->normalizeIpForCompare($row->ip);
            $row->online = $packed !== null && isset($onlineIps[$packed]);
            if (!isset($row->alive_ip_node_id)) {
                $row->alive_ip_node_id = null;
            }
            if (!isset($row->node_id)) {
                $row->node_id = null;
            }
        }

        $aliveNodeIdSet = [];
        foreach ($aliveIps as $row) {
            if (!empty($row->alive_ip_node_id)) {
                $aliveNodeIdSet[(int) $row->alive_ip_node_id] = true;
            }
            if (!empty($row->node_id)) {
                $aliveNodeIdSet[(int) $row->node_id] = true;
            }
        }
        $aliveNodeNames = [];
        if (!empty($aliveNodeIdSet)) {
            $names = DB::table('v2_server_v2node')
                ->whereIn('id', array_keys($aliveNodeIdSet))
                ->pluck('name', 'id');
            foreach ($names as $id => $name) {
                $aliveNodeNames[(string) $id] = (string) $name;
            }
        }

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

        $deviceOnline = 0;
        if (is_array($aliveCache) && isset($aliveCache['alive_ip'])) {
            $deviceOnline = (int) $aliveCache['alive_ip'];
        }
        $deviceLimit = isset($user->device_limit) ? (int) $user->device_limit : 0;

        return response([
            'data' => [
                'user' => [
                    'id' => (int) $user->id,
                    'email' => $user->email,
                    'created_at' => (int) $user->created_at,
                    'expired_at' => $user->expired_at !== null ? (int) $user->expired_at : null,
                    'banned' => (int) $user->banned,
                    'risk_ignore' => isset($user->risk_ignore) ? (int) $user->risk_ignore : 0,
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
                'alive_node_names' => $aliveNodeNames,
                'device_online' => $deviceOnline,
                'device_limit' => $deviceLimit,
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

    private function normalizeIpForCompare($ip): ?string
    {
        return IpNormalizer::packed($ip);
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

        $aliveMainlandRegions = DB::table('v2_alive_ip_history')
            ->where('country', '中国')
            ->whereNotNull('province')
            ->where('province', '!=', '')
            ->whereNotIn('province', ['香港', '澳门', '台湾'])
            ->distinct()->orderBy('province')->pluck('province')->all();

        $aliveOtherRegions = [];
        $aliveCountries = DB::table('v2_alive_ip_history')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->where('country', '!=', '中国')
            ->distinct()->orderBy('country')->pluck('country')->all();
        foreach ($aliveCountries as $country) {
            $aliveOtherRegions[] = [
                'value' => 'country:' . $country,
                'label' => $country,
            ];
        }
        $aliveSpecial = DB::table('v2_alive_ip_history')
            ->where('country', '中国')
            ->whereIn('province', ['香港', '澳门', '台湾'])
            ->distinct()->pluck('province')->all();
        foreach (['香港', '澳门', '台湾'] as $region) {
            if (in_array($region, $aliveSpecial, true)) {
                $aliveOtherRegions[] = [
                    'value' => 'region:' . $region,
                    'label' => $specialLabels[$region],
                ];
            }
        }

        $nodes = DB::table('v2_server_v2node')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response([
            'data' => [
                'mainland_regions' => $mainlandRegions,
                'other_regions' => $otherRegions,
                'alive_mainland_regions' => $aliveMainlandRegions,
                'alive_other_regions' => $aliveOtherRegions,
                'nodes' => $nodes,
            ]
        ]);
    }

    public function getRuleOptions(Request $request)
    {
        $options = Cache::remember('user_portrait_rule_options', 300, function () {
            $countries = SubLog::whereNotNull('ip_country')
                ->where('ip_country', '!=', '')
                ->distinct()
                ->orderBy('ip_country')
                ->pluck('ip_country')
                ->all();
            $countries = array_values(array_unique(array_merge(['中国'], $countries)));

            $fixedRegions = [
                '北京', '天津', '河北', '山西', '内蒙古', '辽宁', '吉林', '黑龙江',
                '上海', '江苏', '浙江', '安徽', '福建', '江西', '山东', '河南',
                '湖北', '湖南', '广东', '广西', '海南', '重庆', '四川', '贵州',
                '云南', '西藏', '陕西', '甘肃', '青海', '宁夏', '新疆',
                '香港', '澳门', '台湾',
            ];
            $regions = SubLog::where('ip_country', '中国')
                ->whereNotNull('ip_region')
                ->where('ip_region', '!=', '')
                ->distinct()
                ->pluck('ip_region')
                ->all();
            $regions = array_values(array_unique(array_merge($fixedRegions, $regions)));

            $cities = [];
            $cityRows = SubLog::where('ip_country', '中国')
                ->whereNotNull('ip_region')
                ->where('ip_region', '!=', '')
                ->whereNotNull('ip_city')
                ->where('ip_city', '!=', '')
                ->get(['ip_region', 'ip_city']);
            foreach ($cityRows as $row) {
                $cities[$row->ip_region][] = $row->ip_city;
            }
            foreach ($cities as $region => $list) {
                $list = array_values(array_unique($list));
                sort($list);
                $cities[$region] = $list;
            }

            $isps = SubLog::whereNotNull('ip_isp')
                ->where('ip_isp', '!=', '')
                ->distinct()
                ->orderBy('ip_isp')
                ->pluck('ip_isp')
                ->all();
            $commonIsps = [
                '电信', '联通', '移动', '广电', '教育网',
                '中国电信', '中国联通', '中国移动', '其他',
            ];
            $isps = array_values(array_unique(array_merge($commonIsps, $isps)));

            return [
                'countries' => $countries,
                'regions' => $regions,
                'cities' => $cities,
                'isps' => $isps,
            ];
        });

        return response(['data' => $options]);
    }

    public function testRule(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|string|max:128',
            'user_agent' => 'nullable|string|max:255',
            'ip_country' => 'nullable|string|max:128',
            'ip_region' => 'nullable|string|max:128',
            'ip_city' => 'nullable|string|max:128',
            'ip_isp' => 'nullable|string|max:128',
            'original_host' => 'required|string|max:255',
            'port' => 'nullable|integer|between:1,65535',
        ]);

        if (!$this->isValidRuleHost($data['original_host'])) {
            throw ValidationException::withMessages([
                'original_host' => '请输入合法的域名或 IP',
            ]);
        }

        $isp = trim((string) ($data['ip_isp'] ?? ''));
        if ($isp !== '') {
            $this->assertValidIspExpression($isp);
        }

        $context = [
            'user_id' => (string) ($data['user_id'] ?? ''),
            'user_agent' => strtolower((string) ($data['user_agent'] ?? '')),
            'country' => (string) ($data['ip_country'] ?? ''),
            'region' => (string) ($data['ip_region'] ?? ''),
            'city' => (string) ($data['ip_city'] ?? ''),
            'isp' => $isp,
            'host' => (string) $data['original_host'],
            'port' => $data['port'] ?? null,
        ];

        $result = (new SubRuleMatcher())->test($context);
        return response(['data' => $result]);
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
        \Illuminate\Support\Facades\Cache::forget('user_portrait_rule_options');
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
            'replace_host' => 'nullable|string|max:255|required_unless:type,1',
            'replace_port' => 'nullable|integer|between:1,65535',
            'type' => 'required|in:0,1',
            'enable' => 'required|in:0,1',
        ]);

        if ((int) $data['type'] === 1) {
            $data['replace_host'] = null;
            $data['replace_port'] = null;
        }

        if (!empty($data['ip_isp'])) {
            $data['ip_isp'] = trim((string) $data['ip_isp']);
            $this->assertValidIspExpression($data['ip_isp']);
        }

        foreach (['original_host', 'replace_host'] as $field) {
            if (!empty($data[$field]) && !$this->isValidRuleHost((string) $data[$field])) {
                throw ValidationException::withMessages([
                    $field => '请输入合法的域名或 IP',
                ]);
            }
        }

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

    private function isValidRuleHost(string $host): bool
    {
        $host = trim($host);
        if ($host === '') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }
        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    private function assertValidIspExpression(string $expression): void
    {
        $expression = trim($expression);
        if ($expression === '') {
            return;
        }
        if (str_contains($expression, ',')) {
            throw ValidationException::withMessages([
                'ip_isp' => '请使用 OR 代替逗号',
            ]);
        }
        if (!(new SubRuleMatcher())->validateIspExpression($expression)) {
            throw ValidationException::withMessages([
                'ip_isp' => '运营商规则格式错误，请检查 AND / OR / NOT: / FULL: 和括号',
            ]);
        }
    }

    public function riskCheck(Request $request)
    {
        $rules = array_values(array_filter($this->riskRules(), function ($rule) {
            return !empty($rule['enabled']) && (!empty($rule['groups']) || !empty($rule['conditions']));
        }));
        if (empty($rules)) {
            return response(['data' => [], 'total' => 0]);
        }

        $ruleIds = array_map(function ($rule) {
            return (string) $rule['id'];
        }, $rules);

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

        $cacheKey = 'sub_risk|' . md5(serialize([$rules, Cache::get('risk_ignore_version', 0)]));

        $filtered = Cache::remember($cacheKey, 60, function () use ($rules, $ruleIds) {
            return app(RiskService::class)->buildRows($rules, $ruleIds, 0);
        });

        // 颜色优先级：红 > 橙 > 黄，取用户命中的最高优先级
        $ruleColorRank = [];
        foreach ($rules as $rule) {
            $color = $rule['color'] ?? 'red';
            $ruleColorRank[(string) $rule['id']] = $color === 'red' ? 0 : ($color === 'orange' ? 1 : 2);
        }
        foreach ($filtered as &$row) {
            $rank = 3;
            foreach ((array) $row['risk_tags'] as $tagId) {
                $value = $ruleColorRank[(string) $tagId] ?? 3;
                if ($value < $rank) {
                    $rank = $value;
                }
            }
            $row['risk_color_rank'] = $rank;
        }
        unset($row);

        usort($filtered, function ($a, $b) use ($sort, $sortType) {
            $rankA = $a['risk_color_rank'] ?? 3;
            $rankB = $b['risk_color_rank'] ?? 3;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            $left = $a[$sort] ?? 0;
            $right = $b[$sort] ?? 0;
            if ($left == $right) {
                return $a['user_id'] <=> $b['user_id'];
            }
            return $sortType === 'ASC' ? ($left <=> $right) : ($right <=> $left);
        });

        $normalRows = [];
        $ignoredRows = [];
        foreach ($filtered as $row) {
            if (!empty($row['ignored'])) {
                $ignoredRows[] = $row;
            } else {
                $normalRows[] = $row;
            }
        }
        $filtered = array_merge($normalRows, $ignoredRows);

        $total = count($filtered);
        $data = array_slice($filtered, ($current - 1) * $pageSize, $pageSize);

        return response([
            'data' => $data,
            'total' => $total,
            'ignored_total' => count($ignoredRows),
        ]);
    }

    public function toggleRiskIgnore(Request $request)
    {
        $userId = (int) $request->input('user_id');
        if ($userId <= 0) {
            abort(500, '用户 ID 错误');
        }
        $exists = DB::table('v2_user')->where('id', $userId)->exists();
        if (!$exists) {
            abort(500, '用户不存在');
        }

        $ignore = (int) $request->input('ignore', 1) === 1 ? 1 : 0;
        try {
            DB::table('v2_user')->where('id', $userId)->update(['risk_ignore' => $ignore]);
        } catch (\Throwable $e) {
            abort(500, '更新失败，请先执行数据库更新以添加 risk_ignore 字段');
        }

        if ($ignore === 1) {
            $this->clearRiskNotifyState($userId);
        }
        Cache::forever('risk_ignore_version', microtime(true));

        return response(['data' => [
            'user_id' => $userId,
            'ignored' => $ignore === 1,
        ]]);
    }

    public function ignoredUsers(Request $request)
    {
        $current = max(1, (int) $request->input('current', 1));
        $pageSize = (int) $request->input('pageSize', 20);
        if ($pageSize < 10) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        try {
            $builder = DB::table('v2_user')->where('risk_ignore', 1);
            $total = (clone $builder)->count();
            $rows = $builder->orderByDesc('id')
                ->forPage($current, $pageSize)
                ->get(['id', 'email']);
        } catch (\Throwable $e) {
            return response(['data' => [], 'total' => 0]);
        }

        return response(['data' => $rows, 'total' => $total]);
    }

    public function clearIgnoredUsers(Request $request)
    {
        $all = (int) $request->input('all', 0) === 1;
        $userId = (int) $request->input('user_id', 0);
        if (!$all && $userId <= 0) {
            abort(500, '参数错误');
        }

        try {
            $query = DB::table('v2_user')->where('risk_ignore', 1);
            if (!$all) {
                $query->where('id', $userId);
            }
            $count = $query->update(['risk_ignore' => 0]);
        } catch (\Throwable $e) {
            abort(500, '更新失败，请先执行数据库更新以添加 risk_ignore 字段');
        }

        Cache::forever('risk_ignore_version', microtime(true));

        return response(['data' => true, 'count' => $count]);
    }

    private function clearRiskNotifyState(int $userId): void
    {
        $state = Cache::get('risk_notify_state', []);
        if (!is_array($state) || empty($state)) {
            return;
        }
        $prefix = $userId . ':';
        foreach (array_keys($state) as $key) {
            if (strpos((string) $key, $prefix) === 0) {
                unset($state[$key]);
            }
        }
        Cache::put('risk_notify_state', $state, 30 * 86400);
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
            $excludeNodeIp = Schema::hasColumn('v2_alive_ip_history', 'alive_ip_node_id');
            $query = function () use ($since, $excludeNodeIp) {
                return DB::table('v2_alive_ip_history')
                    ->where('last_seen_at', '>=', $since)
                    ->where('provider', '!=', 'lan')
                    ->where('country', 'like', '%中国%')
                    ->whereNotNull('province')
                    ->where('province', '!=', '')
                    ->whereNotIn('province', ['香港', '澳门', '台湾'])
                    ->when($excludeNodeIp, function ($builder) {
                        $builder->whereNull('alive_ip_node_id');
                    });
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

    public function fetchAlive(Request $request)
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
            'id', 'user_id', 'ip', 'country', 'province', 'provider',
            'node_id', 'alive_ip_node_id', 'last_seen_at',
        ];
        $sort = in_array($request->input('sort'), $sortWhitelist, true) ? $request->input('sort') : 'last_seen_at';

        $builder = DB::table('v2_alive_ip_history')->orderBy($sort, $sortType);
        $this->applyAliveFilters($request, $builder);

        $total = (clone $builder)->count();
        $rows = $builder->forPage($current, $pageSize)->get();

        $nodeIds = [];
        foreach ($rows as $row) {
            if (!empty($row->node_id)) {
                $nodeIds[(int) $row->node_id] = true;
            }
            if (isset($row->alive_ip_node_id) && !empty($row->alive_ip_node_id)) {
                $nodeIds[(int) $row->alive_ip_node_id] = true;
            }
        }
        $nodeNames = [];
        if (!empty($nodeIds)) {
            $nodeNames = DB::table('v2_server_v2node')
                ->whereIn('id', array_keys($nodeIds))
                ->pluck('name', 'id');
        }

        foreach ($rows as $row) {
            $nodeId = !empty($row->node_id) ? (int) $row->node_id : 0;
            $row->node_name = $nodeId > 0
                ? ($nodeNames[$nodeId] ?? ('节点#' . $nodeId))
                : '';

            $aliveNodeId = isset($row->alive_ip_node_id) ? (int) $row->alive_ip_node_id : 0;
            $row->alive_ip_node_id = $aliveNodeId > 0 ? $aliveNodeId : null;
            $row->alive_ip_node_name = $aliveNodeId > 0
                ? ($nodeNames[$aliveNodeId] ?? ('节点#' . $aliveNodeId))
                : '';
        }

        return response(['data' => $rows, 'total' => $total]);
    }

    public function clearAliveLogs(Request $request)
    {
        $builder = DB::table('v2_alive_ip_history');
        $this->applyAliveFilters($request, $builder);
        $count = $builder->delete();

        foreach (['1h', '1d', '7d', '30d'] as $range) {
            Cache::forget('user_portrait_distribution|' . $range);
        }

        return response(['data' => true, 'count' => $count]);
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

    private function applyAliveFilters(Request $request, $builder)
    {
        if ($request->filled('date_range')) {
            $range = $request->input('date_range');
            if ($range === 'today') {
                $builder->where('last_seen_at', '>=', strtotime('today'));
            } elseif ($range === '7d') {
                $builder->where('last_seen_at', '>=', strtotime('-7 days'));
            } elseif ($range === '30d') {
                $builder->where('last_seen_at', '>=', strtotime('-30 days'));
            }
        }
        if ($request->filled('user_id')) {
            $userId = $request->input('user_id');
            if (preg_match('/^(>=|<=|>|<)(\d+)$/', $userId, $m)) {
                $builder->where('user_id', $m[1], (int) $m[2]);
            } else {
                $builder->where('user_id', (int) $userId);
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
            $builder->where('country', '中国')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->whereNotIn('province', ['香港', '澳门', '台湾']);
            if ($area !== '') {
                $builder->where('province', $area);
            }
        } elseif ($countryGroup === 'other') {
            $builder->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('country')
                        ->where('country', '!=', '')
                        ->where('country', '!=', '中国');
                })->orWhere(function ($q) {
                    $q->where('country', '中国')
                        ->whereIn('province', ['香港', '澳门', '台湾']);
                });
            });
            if ($area !== '') {
                if (str_starts_with($area, 'region:')) {
                    $builder->where('country', '中国')
                        ->where('province', substr($area, 7));
                } else {
                    $country = str_starts_with($area, 'country:') ? substr($area, 8) : $area;
                    $builder->where('country', $country);
                }
            }
        } else {
            if ($request->filled('ip_country')) {
                $builder->where('country', $request->input('ip_country'));
            }
            if ($request->filled('ip_region')) {
                $builder->where('province', $request->input('ip_region'));
            }
        }

        if ($request->filled('ip_city')) {
            $builder->where('city', 'like', '%' . $request->input('ip_city') . '%');
        }
        if ($request->filled('ip_isp')) {
            $builder->where('isp', 'like', '%' . $request->input('ip_isp') . '%');
        }
        if ($request->filled('node_id')) {
            $builder->where('node_id', (int) $request->input('node_id'));
        }

        $ipKind = (string) $request->input('ip_kind', '');
        if (in_array($ipKind, ['user', 'node'], true)
            && Schema::hasColumn('v2_alive_ip_history', 'alive_ip_node_id')) {
            if ($ipKind === 'node') {
                $builder->whereNotNull('alive_ip_node_id');
            } else {
                $builder->whereNull('alive_ip_node_id');
            }
        }
    }
}
