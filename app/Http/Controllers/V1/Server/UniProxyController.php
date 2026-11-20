<?php

namespace App\Http\Controllers\V1\Server;

use App\Http\Controllers\Controller;
use App\Services\ServerService;
use App\Services\UserDistributionService;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MessagePack\Packer;

class UniProxyController extends Controller
{
    private $nodeType;
    private $nodeInfo;
    private $nodeId;
    private $serverService;
    private ?array $ignoredNodeIps = null;

    public function __construct(Request $request)
    {
        $token = $request->input('token');
        if (empty($token)) {
            abort(500, 'token is null');
        }
        if ($token !== config('v2board.server_token')) {
            abort(500, 'token is error');
        }
        $this->nodeType = $request->input('node_type');
        if ($this->nodeType === 'hysteria2') $this->nodeType = 'hysteria';
        $this->nodeId = $request->input('node_id');
        $this->serverService = new ServerService();
        $this->nodeInfo = $this->serverService->getServer($this->nodeId, $this->nodeType);
        if (!$this->nodeInfo) abort(500, 'server is not exist');
    }

    // 后端获取用户
    public function user(Request $request)
    {
        ini_set('memory_limit', -1);
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_LAST_CHECK_AT', $this->nodeInfo->id), time(), 3600);
        $users = $this->serverService->getAvailableUsers($this->nodeInfo->group_id)
            ->map(function ($user) {
                return array_filter($user->toArray(), function ($v) {
                    return !is_null($v);
                });
            })->toArray();

        $response['users'] = $users;
        if (strpos((string) $request->header('X-Response-Format'), 'msgpack') !== false) {
            $packer = new Packer();
            $response = $packer->pack($response);
            $eTag = sha1($response);
            if (strpos((string) $request->header('If-None-Match'), $eTag) !== false) {
                abort(304);
            }

            return response($response, 200, ['Content-Type' => 'application/x-msgpack'])->header('ETag', "\"{$eTag}\"");
        } else {
            $eTag = sha1(json_encode($response));
            if (strpos((string) $request->header('If-None-Match'), $eTag) !== false) {
                abort(304);
            }

            return response($response)->header('ETag', "\"{$eTag}\"");
        }
    }

    // 后端提交数据
    public function push(Request $request)
    {
        $data = $request->json()->all();
        if (empty($data)) {
            $data = $_POST;
        }
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            // JSON decoding error
            return response([
                'error' => 'Invalid traffic data'
            ], 400);
        }
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_ONLINE_USER', $this->nodeInfo->id), count($data), 3600);
        Cache::put(CacheKey::get('SERVER_' . strtoupper($this->nodeType) . '_LAST_PUSH_AT', $this->nodeInfo->id), time(), 3600);
        $userService = new UserService();
        $userService->trafficFetch($this->nodeInfo->toArray(), $this->nodeType, $data);

        return response([
            'data' => true
        ]);
    }

    // 后端获取在线数据
    public function alivelist(Request $request)
    {
        $alive = Cache::remember('ALIVE_LIST', 60, function () {
            $userService = new UserService();
            $users = $userService->getDeviceLimitedUsers();

            if ($users->isEmpty()) {
                return [];
            }

            $keys = [];
            $idMap = [];
            foreach ($users as $user) {
                $key = 'ALIVE_IP_USER_' . $user->id;
                $keys[] = $key;
                $idMap[$key] = $user->id;
            }

            $results = Cache::many($keys);
            $alive = [];
            foreach ($results as $key => $data) {
                if (is_array($data) && isset($data['alive_ip'])) {
                    $alive[$idMap[$key]] = $data['alive_ip'];
                }
            }
            return $alive;
        });
        return response()->json(['alive' => (object)$alive]);
    }

    // 后端提交在线数据
    public function alive(Request $request)
    {
        $this->rememberNodeIps($request);

        $data = $request->json()->all();
        if (empty($data)) {
            $data = $_POST;
        }
        if (empty($data)) {
            return response([
                'data' => true
            ]);
        }
        if (!is_array($data)) {
            return response([
                'error' => 'Invalid online data format'
            ], 400);
        }
        $updateAt = time();
        $cacheKeys = array_map(function ($uid) {
            return 'ALIVE_IP_USER_' . $uid;
        }, array_keys($data));

        if (empty($cacheKeys)) {
            return response([
                'data' => true
            ]);
        }

        $cachedData = Cache::many($cacheKeys);
        $updates = [];
        $recordData = [];
        $chainForwardIgnore = (int) config('v2board.device_limit_chain_forward', 0) === 1;

        foreach ($data as $uid => $ips) {
            if (!is_numeric($uid) || !is_array($ips)) {
                continue; // 跳过无效数据
            }
            $key = 'ALIVE_IP_USER_' . $uid;
            $ips_array = $cachedData[$key] ?? [];

            // 更新节点数据
            $ips_array[$this->nodeType . $this->nodeId] = ['aliveips' => $ips, 'lastupdateAt' => $updateAt];
            // 清理过期数据
            foreach ($ips_array as $nodetypeid => $oldips) {
                if ($nodetypeid !== 'alive_ip' && is_array($oldips) && ($updateAt - ($oldips['lastupdateAt'] ?? 0) > 100)) {
                    unset($ips_array[$nodetypeid]);
                }
            }

            // 汇总所有节点的来源 IP，忽略对接节点 IP（每请求只读一次缓存）
            $ignoredIps = [];
            if ($chainForwardIgnore) {
                $ignoredIps = $this->chainForwardIgnoredIps();
            }

            // 计算活跃IP数量
            $count = 0;
            if (config('v2board.device_limit_mode', 0) == 1) {
                $ipmap = [];
                foreach ($ips_array as $nodetypeid => $newdata) {
                    if ($nodetypeid !== 'alive_ip' && is_array($newdata) && isset($newdata['aliveips'])) {
                        foreach ($newdata['aliveips'] as $ip_NodeId) {
                            $ip = explode("_", $ip_NodeId)[0];
                            $packed = $this->normalizeIpAddress($ip);
                            if ($chainForwardIgnore && $packed !== null && isset($ignoredIps[$packed])) {
                                continue;
                            }
                            $ipmap[$ip] = 1;
                        }
                    }
                }
                $count = count($ipmap);
            } else {
                foreach ($ips_array as $nodetypeid => $newdata) {
                    if ($nodetypeid !== 'alive_ip' && is_array($newdata) && isset($newdata['aliveips'])) {
                        if (!$chainForwardIgnore) {
                            $count += count($newdata['aliveips']);
                            continue;
                        }
                        foreach ($newdata['aliveips'] as $ip_NodeId) {
                            $ip = explode("_", $ip_NodeId)[0];
                            $packed = $this->normalizeIpAddress($ip);
                            if ($packed === null || !isset($ignoredIps[$packed])) {
                                $count++;
                            }
                        }
                    }
                }
            }
            $ips_array['alive_ip'] = $count;

            // 在线记录同样剔除节点 IP
            if ($chainForwardIgnore) {
                $recordIps = [];
                foreach ($ips as $ipNodeId) {
                    $ip = explode("_", (string) $ipNodeId)[0];
                    $packed = $this->normalizeIpAddress($ip);
                    if ($packed !== null && isset($ignoredIps[$packed])) {
                        continue;
                    }
                    $recordIps[] = $ipNodeId;
                }
                $recordData[$uid] = $recordIps;
            } else {
                $recordData[$uid] = $ips;
            }

            $updates[$key] = $ips_array;
        }

        // 批量更新缓存
        foreach ($updates as $key => $value) {
            Cache::put($key, $value, 120);
        }

        try {
            (new UserDistributionService())->record(
                $recordData,
                (int) $this->nodeInfo->id,
                (string) $this->nodeInfo->name
            );
        } catch (\Throwable $e) {
            Log::warning('record alive ip history failed: ' . $e->getMessage());
        }

        return response([
            'data' => true
        ]);
    }

    /**
     * 记录节点访问 /alive 的来源 IP，同一节点可能对应多个 IP。
     */
    private function rememberNodeIps(Request $request): void
    {
        $now = time();
        $sources = [];
        foreach ([$_SERVER['REMOTE_ADDR'] ?? '', $request->ip()] as $source) {
            $canonical = $this->canonicalIpAddress($source);
            if ($canonical !== null) {
                $sources[$canonical] = true;
            }
        }
        if (empty($sources)) {
            return;
        }

        $ips = array_keys($sources);
        $write = function () use ($ips, $now) {
            // 单节点来源集合
            $this->rememberNodeIpSet('ALIVE_NODE_IPS_' . $this->nodeType . $this->nodeId, $ips, $now);
            // 全局节点来源集合，统计时不再依赖用户缓存里有哪些节点
            $this->rememberNodeIpSet('ALIVE_NODE_IPS_ALL', $ips, $now);
        };

        // 多个节点可能同时上报，用锁避免读改写互相覆盖
        try {
            Cache::lock('ALIVE_NODE_IPS_LOCK', 3)->block(2, $write);
        } catch (\Throwable $e) {
            $write();
        }
    }

    private function rememberNodeIpSet(string $key, array $ips, int $now): void
    {
        $set = Cache::get($key, []);
        if (!is_array($set)) {
            $set = [];
        }
        foreach ($ips as $ip) {
            $set[$ip] = $now;
        }
        foreach ($set as $ip => $ts) {
            if (!is_int($ts) || $now - $ts > 1800) {
                unset($set[$ip]);
            }
        }
        Cache::put($key, $set, 3600);
    }

    /**
     * 汇总全部节点来源 IP。每请求只读一次缓存，结果与上报用户数无关。
     */
    private function chainForwardIgnoredIps(): array
    {
        if ($this->ignoredNodeIps === null) {
            $this->ignoredNodeIps = $this->packedIpSet(Cache::get('ALIVE_NODE_IPS_ALL', []));
        }
        return $this->ignoredNodeIps;
    }

    private function packedIpSet($set): array
    {
        $packed = [];
        if (!is_array($set)) {
            return $packed;
        }
        foreach (array_keys($set) as $ip) {
            $value = $this->normalizeIpAddress($ip);
            if ($value !== null) {
                $packed[$value] = true;
            }
        }
        return $packed;
    }

    private function canonicalIpAddress($ip): ?string
    {
        $packed = $this->normalizeIpAddress($ip);
        if ($packed === null) {
            return null;
        }
        $canonical = @inet_ntop($packed);
        return $canonical === false ? null : $canonical;
    }

    private function normalizeIpAddress($ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return null;
        }
        if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $ip, $matches)) {
            $ip = $matches[1];
        }
        $ip = preg_replace('/%.*$/', '', $ip);
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $matches)) {
            $ip = $matches[1];
        }
        if (stripos($ip, '::ffff:') === 0) {
            $v4 = substr($ip, 7);
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip = $v4;
            }
        }
        $packed = @inet_pton($ip);
        return $packed === false ? null : $packed;
    }

    // 后端获取配置
    public function config(Request $request)
    {
        switch ($this->nodeType) {
            case 'shadowsocks':
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'cipher' => $this->nodeInfo->cipher,
                    'obfs' => $this->nodeInfo->obfs,
                    'obfs_settings' => $this->nodeInfo->obfs_settings
                ];

                if ($this->nodeInfo->cipher === '2022-blake3-aes-128-gcm') {
                    $response['server_key'] = Helper::getServerKey($this->nodeInfo->created_at, 16);
                }
                if ($this->nodeInfo->cipher === '2022-blake3-aes-256-gcm') {
                    $response['server_key'] = Helper::getServerKey($this->nodeInfo->created_at, 32);
                }
                break;
            case 'vless':
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'network' => $this->nodeInfo->network,
                    'networkSettings' => $this->nodeInfo->network_settings,
                    'tls' => $this->nodeInfo->tls,
                    'flow' => $this->nodeInfo->flow,
                    'tls_settings' => $this->nodeInfo->tls_settings,
                    'encryption' => $this->nodeInfo->encryption,
                    'encryption_settings' => $this->nodeInfo->encryption_settings
                ];
                break;
            case 'tuic':
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'server_name' => $this->nodeInfo->server_name,
                    'congestion_control' => $this->nodeInfo->congestion_control,
                    'zero_rtt_handshake' => $this->nodeInfo->zero_rtt_handshake ? true : false,
                ];
                break;
            case 'hysteria':
                $response = [
                    'version' => $this->nodeInfo->version,
                    'host' => $this->nodeInfo->host,
                    'server_port' => $this->nodeInfo->server_port,
                    'server_name' => $this->nodeInfo->server_name,
                    'up_mbps' => $this->nodeInfo->up_mbps,
                    'down_mbps' => $this->nodeInfo->down_mbps
                ];
                if ($this->nodeInfo->version == 1) {
                    $response['obfs'] = $this->nodeInfo->obfs_password ?? null;
                } elseif ($this->nodeInfo->version == 2) {
                    if ($this->nodeInfo->up_mbps == 0 && $this->nodeInfo->down_mbps == 0) {
                        $response['ignore_client_bandwidth'] = true;
                    } else {
                        $response['ignore_client_bandwidth'] = false;
                    }
                    $response['obfs'] = $this->nodeInfo->obfs ?? null;
                    $response['obfs-password'] = $this->nodeInfo->obfs_password ?? null;
                }
                break;
            case 'anytls':
                $response = [
                    'server_port' => $this->nodeInfo->server_port,
                    'server_name' => $this->nodeInfo->server_name,
                    'padding_scheme' => $this->nodeInfo->padding_scheme
                ];
                break;
        }
        $response['base_config'] = [
            'push_interval' => (int)config('v2board.server_push_interval', 60),
            'pull_interval' => (int)config('v2board.server_pull_interval', 60)
        ];
        if ($this->nodeInfo['route_id']) {
            $response['routes'] = $this->serverService->getRoutes($this->nodeInfo['route_id']);
        }
        $eTag = sha1(json_encode($response));
        if (strpos((string) $request->header('If-None-Match'), $eTag) !== false) {
            abort(304);
        }

        return response($response)->header('ETag', "\"{$eTag}\"");
    }
}
