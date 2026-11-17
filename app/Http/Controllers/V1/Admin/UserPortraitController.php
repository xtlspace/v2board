<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AliveIpHistory;
use App\Models\SubLog;
use App\Models\SubRule;
use App\Services\UserDistributionService;
use App\Utils\ConfigCache;
use App\Utils\IPLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    public function getSettings()
    {
        return response(['data' => [
            'ip_lookup_url' => config('v2board.ip_lookup_url', 'https://iplark.com/'),
            'ip_db_v4_path' => (string) config('v2board.ip_db_v4_path', ''),
            'ip_db_v6_path' => (string) config('v2board.ip_db_v6_path', ''),
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

        $config = config('v2board') ?? [];
        $config['ip_lookup_url'] = $url;
        $config['ip_db_v4_path'] = $v4Path;
        $config['ip_db_v6_path'] = $v6Path;
        $exported = var_export($config, 1);
        if (!ConfigCache::write(base_path() . '/config/v2board.php', "<?php\n return $exported ;")) {
            abort(500, '保存失败');
        }
        config([
            'v2board.ip_lookup_url' => $url,
            'v2board.ip_db_v4_path' => $v4Path,
            'v2board.ip_db_v6_path' => $v6Path,
        ]);

        // IP 库可能已更换，让在线记录的归属地缓存立即失效
        try {
            UserDistributionService::bumpLocationEpoch();
        } catch (\Throwable $e) {
            Log::warning('user_portrait.saveSettings bump epoch failed: ' . $e->getMessage());
        }

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
        try {
            $location = new IPLocation($v4Path === '' ? null : $v4Path, $v6Path === '' ? null : $v6Path);
            $info = $location->find($ip);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        }

        return response(['data' => $info ?: null]);
    }

    public function rebuildLocation(Request $request)
    {
        $table = $request->input('table');
        if (!in_array($table, ['sub', 'alive'], true)) {
            abort(500, '参数错误');
        }
        $tableName = $table === 'sub' ? 'v2_sub_log' : 'v2_alive_ip_history';
        $lastId = max(0, (int) $request->input('last_id', 0));
        $limit = (int) $request->input('limit', 500);
        if ($limit < 50) {
            $limit = 50;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        try {
            $rows = DB::table($tableName)
                ->where('id', '>', $lastId)
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
                'total' => $lastId === 0 ? DB::table($tableName)->count() : null,
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
        $typeWhitelist = ['mismatch', 'online_province', 'online_ip', 'sub_province', 'overseas'];
        $types = array_values(array_intersect(
            array_filter(explode(',', (string) $request->input('types', ''))),
            $typeWhitelist
        ));
        if (empty($types)) {
            $types = ['mismatch', 'online_province', 'online_ip'];
        }

        $provinceThreshold = max(1, (int) $request->input('province_threshold', 3));
        $ipThreshold = max(1, (int) $request->input('ip_threshold', 5));
        $subProvinceThreshold = max(1, (int) $request->input('sub_province_threshold', 3));
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

        $cacheKey = 'sub_risk|' . md5(serialize([
            $types, $provinceThreshold, $ipThreshold, $subProvinceThreshold, $days,
        ]));

        $rows = Cache::remember($cacheKey, 60, function () use ($provinceThreshold, $ipThreshold, $subProvinceThreshold, $days) {
            $since = $days > 0 ? time() - $days * 86400 : 0;

            $subRows = DB::table('v2_sub_log')
                ->select('user_id')
                ->selectRaw("GROUP_CONCAT(DISTINCT ip_region ORDER BY ip_region SEPARATOR ',') AS regions")
                ->selectRaw('COUNT(DISTINCT ip_region) AS region_count')
                ->selectRaw('COUNT(DISTINCT user_agent) AS ua_count')
                ->selectRaw('COUNT(DISTINCT ip) AS sub_ip_count')
                ->selectRaw('MAX(created_at) AS last_at')
                ->selectRaw("SUM(CASE WHEN COALESCE(ip_country, '') NOT IN ('中国', '')
                    OR ip_region IN ('香港', '澳门', '台湾') THEN 1 ELSE 0 END) AS overseas_count")
                ->when($since > 0, function ($query) use ($since) {
                    $query->where('created_at', '>=', $since);
                })
                ->groupBy('user_id')
                ->get();

            $aliveRows = DB::table('v2_alive_ip_history')
                ->select('user_id')
                ->selectRaw("GROUP_CONCAT(DISTINCT province ORDER BY province SEPARATOR ',') AS provinces")
                ->selectRaw('COUNT(DISTINCT province) AS province_count')
                ->selectRaw('COUNT(DISTINCT ip) AS alive_ip_count')
                ->selectRaw('MAX(last_seen_at) AS last_seen_at')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->when($since > 0, function ($query) use ($since) {
                    $query->where('last_seen_at', '>=', $since);
                })
                ->groupBy('user_id')
                ->get();

            $users = [];
            $blank = [
                'sub_regions' => [],
                'alive_provinces' => [],
                'sub_region_count' => 0,
                'ua_count' => 0,
                'sub_ip_count' => 0,
                'alive_province_count' => 0,
                'alive_ip_count' => 0,
                'overseas_count' => 0,
                'last_at' => 0,
                'last_seen_at' => 0,
            ];

            foreach ($aliveRows as $row) {
                $users[$row->user_id] = array_merge($blank, [
                    'user_id' => (int) $row->user_id,
                    'alive_provinces' => $row->provinces ? explode(',', $row->provinces) : [],
                    'alive_province_count' => (int) $row->province_count,
                    'alive_ip_count' => (int) $row->alive_ip_count,
                    'last_seen_at' => (int) $row->last_seen_at,
                ]);
            }

            foreach ($subRows as $row) {
                $id = $row->user_id;
                if (!isset($users[$id])) {
                    $users[$id] = array_merge($blank, ['user_id' => (int) $id]);
                }
                $users[$id]['sub_regions'] = $row->regions ? explode(',', $row->regions) : [];
                $users[$id]['sub_region_count'] = (int) $row->region_count;
                $users[$id]['ua_count'] = (int) $row->ua_count;
                $users[$id]['sub_ip_count'] = (int) $row->sub_ip_count;
                $users[$id]['overseas_count'] = (int) $row->overseas_count;
                $users[$id]['last_at'] = (int) $row->last_at;
            }

            $list = [];
            foreach ($users as $item) {
                $tags = [];
                if (!empty($item['sub_regions']) && !empty($item['alive_provinces'])
                    && !array_intersect($item['sub_regions'], $item['alive_provinces'])) {
                    $tags[] = 'mismatch';
                }
                if ($item['alive_province_count'] >= $provinceThreshold) {
                    $tags[] = 'online_province';
                }
                if ($item['alive_ip_count'] >= $ipThreshold) {
                    $tags[] = 'online_ip';
                }
                if ($item['sub_region_count'] >= $subProvinceThreshold) {
                    $tags[] = 'sub_province';
                }
                if ($item['overseas_count'] > 0) {
                    $tags[] = 'overseas';
                }
                if (empty($tags)) {
                    continue;
                }
                $item['risk_tags'] = $tags;
                $item['risk_count'] = count($tags);
                $list[] = $item;
            }

            return $list;
        });

        $filtered = array_values(array_filter($rows, function ($item) use ($types) {
            return (bool) array_intersect($types, $item['risk_tags']);
        }));

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
                    ->whereNotNull('province')
                    ->where('province', '!=', '');
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
