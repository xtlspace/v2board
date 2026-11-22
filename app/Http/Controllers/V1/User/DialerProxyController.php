<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DialerProxyController extends Controller
{
    private const TCP_PROTOCOLS = ['shadowsocks', 'vless', 'anytls'];

    public function fetch(Request $request)
    {
        $enabled = (int) config('v2board.dialer_proxy_enable', 0) === 1;
        if (!$enabled) {
            return response(['data' => [
                'enabled' => false,
                'in_nodes' => [],
                'out_nodes' => [],
                'rules' => [],
            ]]);
        }

        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, '用户不存在');
        }
        $servers = (new ServerService())->getAvailableServers($user);

        $inNodes = [];
        $outNodes = [];
        foreach ($servers as $server) {
            if ((int) ($server['dialer_proxy_in'] ?? 0) === 1) {
                $inNodes[] = [
                    'id' => (int) $server['id'],
                    'name' => (string) $server['name'],
                ];
            }
            if ((int) ($server['dialer_proxy_out'] ?? 0) === 1
                && in_array($server['protocol'] ?? '', self::TCP_PROTOCOLS, true)) {
                $outNodes[] = [
                    'id' => (int) $server['id'],
                    'name' => (string) $server['name'],
                ];
            }
        }

        $rules = DB::table('v2_user_dialer_proxy')
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get(['in_node_id', 'out_node_id']);

        $inIds = array_column($inNodes, 'id');
        $outIds = array_column($outNodes, 'id');
        $cleanRules = [];
        foreach ($rules as $rule) {
            $inId = (int) $rule->in_node_id;
            $outId = (int) $rule->out_node_id;
            if ($inId === $outId) {
                continue;
            }
            if (!in_array($inId, $inIds, true) || !in_array($outId, $outIds, true)) {
                continue;
            }
            $cleanRules[] = ['in_node_id' => $inId, 'out_node_id' => $outId];
        }

        return response(['data' => [
            'enabled' => true,
            'in_nodes' => $inNodes,
            'out_nodes' => $outNodes,
            'rules' => $cleanRules,
        ]]);
    }

    public function save(Request $request)
    {
        if ((int) config('v2board.dialer_proxy_enable', 0) !== 1) {
            abort(500, '未开启链式代理');
        }

        $user = User::find($request->user['id']);
        if (!$user) {
            abort(500, '用户不存在');
        }
        $rules = $request->input('rules', []);
        if (!is_array($rules)) {
            abort(500, '参数错误');
        }

        $servers = (new ServerService())->getAvailableServers($user);
        $inIds = [];
        $outIds = [];
        foreach ($servers as $server) {
            if ((int) ($server['dialer_proxy_in'] ?? 0) === 1) {
                $inIds[(int) $server['id']] = true;
            }
            if ((int) ($server['dialer_proxy_out'] ?? 0) === 1
                && in_array($server['protocol'] ?? '', self::TCP_PROTOCOLS, true)) {
                $outIds[(int) $server['id']] = true;
            }
        }

        $clean = [];
        $seenOut = [];
        foreach (array_slice($rules, 0, 20) as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $inId = (int) ($rule['in_node_id'] ?? 0);
            $outId = (int) ($rule['out_node_id'] ?? 0);
            if ($inId <= 0 || $outId <= 0 || $inId === $outId) {
                continue;
            }
            if (!isset($inIds[$inId]) || !isset($outIds[$outId])) {
                continue;
            }
            if (isset($seenOut[$outId])) {
                continue;
            }
            $seenOut[$outId] = true;
            $clean[] = [
                'user_id' => $user->id,
                'in_node_id' => $inId,
                'out_node_id' => $outId,
                'created_at' => time(),
                'updated_at' => time(),
            ];
        }

        DB::transaction(function () use ($user, $clean) {
            DB::table('v2_user_dialer_proxy')->where('user_id', $user->id)->delete();
            if (!empty($clean)) {
                DB::table('v2_user_dialer_proxy')->insert($clean);
            }
        });

        return response(['data' => true]);
    }
}
