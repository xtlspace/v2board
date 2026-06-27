<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubLog;
use App\Models\SubRule;
use App\Models\ServerVmess;
use App\Models\ServerTrojan;
use App\Models\ServerShadowsocks;
use App\Models\ServerVless;
use App\Models\ServerHysteria;
use App\Models\ServerTuic;
use App\Models\ServerAnytls;
use App\Models\ServerV2node;
use Illuminate\Http\Request;

class SubLogController extends Controller
{
    public function fetch(Request $request)
    {
        $current = $request->input('current') ?: 1;
        $pageSize = $request->input('pageSize') >= 10 ? $request->input('pageSize') : 10;
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ?: 'id';

        $builder = SubLog::orderBy($sort, $sortType);

        $this->applyFilters($request, $builder);

        $total = $builder->count();
        $res = $builder->forPage((int)$current, (int)$pageSize)->get();

        return response(['data' => $res, 'total' => $total]);
    }

    public function getFilterOptions(Request $request)
    {
        $countries = SubLog::whereNotNull('ip_country')
            ->where('ip_country', '!=', '')
            ->distinct()->orderBy('ip_country')->pluck('ip_country');
        $regions = SubLog::whereNotNull('ip_region')
            ->where('ip_region', '!=', '')
            ->distinct()->orderBy('ip_region')->pluck('ip_region');
        $isps = SubLog::whereNotNull('ip_isp')
            ->where('ip_isp', '!=', '')
            ->distinct()->orderBy('ip_isp')->pluck('ip_isp');

        return response([
            'data' => [
                'countries' => $countries,
                'regions' => $regions,
                'isps' => $isps,
            ]
        ]);
    }

    public function getServers(Request $request)
    {
        $servers = [];
        $models = [
            'vmess' => ServerVmess::class,
            'trojan' => ServerTrojan::class,
            'shadowsocks' => ServerShadowsocks::class,
            'vless' => ServerVless::class,
            'hysteria' => ServerHysteria::class,
            'tuic' => ServerTuic::class,
            'anytls' => ServerAnytls::class,
            'v2node' => ServerV2node::class,
        ];
        foreach ($models as $type => $model) {
            $rows = $model::where('show', 1)->orderBy('sort')->get(['id', 'name', 'host', 'port', 'group_id']);
            foreach ($rows as $row) {
                $servers[] = [
                    'type' => $type,
                    'id' => $row->id,
                    'name' => $row->name,
                    'host' => $row->host,
                    'port' => $row->port,
                    'group_id' => $row->group_id,
                ];
            }
        }
        return response(['data' => $servers]);
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

    private function applyFilters(Request $request, $builder)
    {
        if ($request->filled('user_id')) {
            $builder->where('user_id', (int)$request->input('user_id'));
        }
        if ($request->filled('user_agent')) {
            $keyword = mb_strtolower($request->input('user_agent'));
            $builder->whereRaw('LOWER(user_agent) LIKE ?', ['%' . $keyword . '%']);
        }
        if ($request->filled('ip')) {
            $builder->where('ip', 'like', '%' . $request->input('ip') . '%');
        }
        if ($request->filled('ip_country')) {
            $builder->where('ip_country', $request->input('ip_country'));
        }
        if ($request->filled('ip_region')) {
            $builder->where('ip_region', $request->input('ip_region'));
        }
        if ($request->filled('ip_city')) {
            $builder->where('ip_city', 'like', '%' . $request->input('ip_city') . '%');
        }
        if ($request->filled('ip_isp')) {
            $builder->where('ip_isp', $request->input('ip_isp'));
        }
    }
}
