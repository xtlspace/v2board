<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AliveIpHistory;
use App\Models\SubLog;
use App\Models\SubRule;
use App\Utils\IPLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
                'alive_ips' => $aliveIps,
                'alive_total' => $aliveTotal,
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
        $sort = in_array($request->input('sort'), ['city_count', 'ua_count']) ? $request->input('sort') : 'city_count';
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $cacheKey = 'sub_risk|' . md5(serialize($request->only(['city_threshold', 'ua_threshold', 'current', 'pageSize', 'sort', 'sort_type'])));
        $result = Cache::remember($cacheKey, 60, function () use ($request, $sort, $sortType) {
            $cityThreshold = (int)$request->input('city_threshold', 5);
            $uaThreshold = (int)$request->input('ua_threshold', 3);
            $current = (int)$request->input('current', 1);
            $pageSize = (int)$request->input('pageSize', 10);

            $builder = SubLog::select('user_id')
                ->selectRaw('COUNT(DISTINCT ip_city) as city_count')
                ->selectRaw('COUNT(DISTINCT user_agent) as ua_count')
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw('MAX(created_at) as last_time')
                ->groupBy('user_id')
                ->havingRaw('city_count > ? OR ua_count > ?', [$cityThreshold, $uaThreshold])
                ->orderBy($sort, $sortType)
                ->orderBy('user_id', 'ASC');

            $sql = $builder->toSql();
            $bindings = $builder->getBindings();
            $total = (int)\DB::selectOne('SELECT COUNT(*) as aggregate FROM (' . $sql . ') as sub', $bindings)->aggregate;

            $data = $builder->forPage($current, $pageSize)->get();

            $userIds = $data->pluck('user_id')->toArray();
            if (!empty($userIds)) {
                $users = \App\Models\User::whereIn('id', $userIds)->pluck('email', 'id');
                $data->transform(function ($item) use ($users) {
                    $item['email'] = $users->get($item->user_id, '');
                    return $item;
                });
            }

            return ['data' => $data, 'total' => $total];
        });

        return response($result);
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
