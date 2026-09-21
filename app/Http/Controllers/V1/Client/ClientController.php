<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Protocols\General;
use App\Protocols\Singbox\Singbox;
use App\Protocols\Singbox\SingboxOld;
use App\Protocols\ClashMeta;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use App\Models\SubLog;
use App\Models\SubRule;
use App\Utils\IPLocation;

class ClientController extends Controller
{
    public function subscribe(Request $request)
    {
        $flag = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $flag = strtolower($flag);
        $user = $request->user;
        // 记录订阅请求日志
        $subLog = new SubLog();
        $subLog->user_id = $user->id;
        $subLog->ip = $_SERVER['REMOTE_ADDR'] ?? $request->ip();
        $subLog->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $location = null;
        try {
            $ipLocation = new IPLocation();
            $location = $ipLocation->find($subLog->ip);
            if ($location) {
                $subLog->ip_country = $location['country'] ?: null;
                $subLog->ip_region = $location['region'] ?: null;
                $subLog->ip_city = $location['city'] ?: null;
                $subLog->ip_isp = $location['isp'] ?: null;
            }
        } catch (\Exception $e) {
            // IP定位失败不影响订阅功能
        }
        $subLog->save();

        // account is banned
        if ($user->banned) {
            return;
        }

        // build reminder node if configured
        $reminder = $this->buildReminderServer($user);
        if ($reminder !== null) {
            return $this->renderSubscription($reminder['user'], [$reminder['server']], $flag);
        }

        // account not expired and is not banned.
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            $servers = $this->applySubRules($servers, $subLog, $location);
            if ($flag && !strpos($flag, 'sing')) {
                $this->setSubscribeInfoToServers($servers, $user);
            }
            return $this->renderSubscription($user, $servers, $flag);
        }
    }

    private function renderSubscription($user, $servers, $flag)
    {
        if ($flag) {
            if (!strpos($flag, 'sing')) {
                foreach (array_reverse(glob(app_path('Protocols') . '/*.php')) as $file) {
                    $file = 'App\\Protocols\\' . basename($file, '.php');
                    $class = new $file($user, $servers);
                    if (strpos($flag, $class->flag) !== false) {
                        return $class->handle();
                    }
                }
            }
            if (strpos($flag, 'sing') !== false) {
                $version = null;
                if (preg_match('/sing-box\s+([0-9.]+)/i', $flag, $matches)) {
                    $version = $matches[1];
                }
                if (!is_null($version) && $version >= '1.12.0') {
                    $class = new Singbox($user, $servers);
                } else {
                    $class = new SingboxOld($user, $servers);
                }
                return $class->handle();
            }
        }
        $class = new General($user, $servers);
        return $class->handle();
    }

    private function buildReminderServer($user)
    {
        if ((int)config('v2board.sub_reminder_enable', 0) !== 1) return null;
        $node = json_decode(config('v2board.sub_reminder_node', ''), true);
        if (!is_array($node) || empty($node['host']) || empty($node['port'])) return null;

        if ($user->transfer_enable <= 0) {
            $state = 'no_sub';
        } elseif ($user->expired_at !== NULL && $user->expired_at <= time()) {
            $state = 'expired';
        } elseif ($user->u + $user->d >= $user->transfer_enable) {
            $state = 'exhaust';
        } else {
            return null;
        }

        $name = config('v2board.' . [
                'no_sub' => 'sub_reminder_name_no_sub',
                'expired' => 'sub_reminder_name_expired',
                'exhaust' => 'sub_reminder_name_exhaust',
            ][$state], '');
        if (empty($name)) {
            $name = [
                'no_sub' => '您尚未购买订阅',
                'expired' => '您的订阅已过期',
                'exhaust' => '您的流量已用尽',
            ][$state];
        }

        $tlsSettings = isset($node['tls_settings']) && is_array($node['tls_settings']) ? $node['tls_settings'] : [];
        if (!isset($tlsSettings['server_name'])) {
            $tlsSettings['server_name'] = $node['host'];
        }

        $server = [
            'type' => 'v2node',
            'name' => $name,
            'host' => $node['host'],
            'port' => $node['port'],
            'protocol' => $node['protocol'] ?? 'vless',
            'tls' => ($node['tls'] ?? 0) ? 1 : 0,
            'tls_settings' => $tlsSettings,
            'network' => $node['network'] ?? 'tcp',
            'network_settings' => isset($node['network_settings']) && is_array($node['network_settings']) ? $node['network_settings'] : [],
            'encryption' => $node['encryption'] ?? null,
            'encryption_settings' => isset($node['encryption_settings']) && is_array($node['encryption_settings']) ? $node['encryption_settings'] : [],
            'flow' => $node['flow'] ?? null,
            'cipher' => $node['cipher'] ?? null,
            'obfs' => $node['obfs'] ?? null,
            'obfs-host' => $node['obfs-host'] ?? null,
            'obfs-path' => $node['obfs-path'] ?? null,
            'created_at' => time(),
            'is_online' => 1,
        ];

        $renderUser = $user;
        if (!empty($node['uuid'])) {
            $renderUser = clone $user;
            $renderUser->uuid = $node['uuid'];
        }

        return [
            'user' => $renderUser,
            'server' => $server,
        ];
    }

    private function setSubscribeInfoToServers(&$servers, $user)
    {
        if (!isset($servers[0])) return;
        if (!(int)config('v2board.show_info_to_server_enable', 0)) return;
        $useTraffic = $user['u'] + $user['d'];
        $totalTraffic = $user['transfer_enable'];
        $remainingTraffic = Helper::trafficConvert($totalTraffic - $useTraffic);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : '长期有效';
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        array_push($servers, array_merge($servers[0], [
            'name' => "到期时间：{$expiredDate}，剩余流量：{$remainingTraffic}",
        ]));
    }

    private function applySubRules($servers, $subLog, $location)
    {
        $rules = \Illuminate\Support\Facades\Cache::remember('sub_rules', 60, function () {
            return SubRule::where('enable', 1)->orderBy('sort')->orderBy('id')->get();
        });
        if ($rules->isEmpty()) return $servers;

        $ua = strtolower($subLog->user_agent ?? '');
        $country = $location['country'] ?? '';
        $region = $location['region'] ?? '';
        $city = $location['city'] ?? '';
        $isp = $location['isp'] ?? '';

        foreach ($servers as $key => $server) {
            foreach ($rules as $rule) {
                if (!$this->matchField($rule->user_agent, $ua, false)) continue;
                if (!$this->matchField($rule->ip_country, $country)) continue;
                if (!$this->matchField($rule->ip_region, $region)) continue;
                if (!$this->matchField($rule->ip_city, $city)) continue;
                if (!$this->matchField($rule->ip_isp, $isp)) continue;
                if (!$this->matchField($rule->user_id, (string)$subLog->user_id)) continue;
                if ($rule->original_host && $rule->original_host !== $server['host']) continue;
                if ((int)$rule->type === 1) break;
                $servers[$key]['host'] = $rule->replace_host;
                if ($rule->replace_port) $servers[$key]['port'] = $rule->replace_port;
                break;
            }
        }
        return $servers;
    }

    private function matchField($ruleValue, $actualValue, $exact = true)
    {
        if (empty($ruleValue)) return true;
        $not = str_starts_with($ruleValue, 'NOT:');
        if ($not) $ruleValue = substr($ruleValue, strlen('NOT:'));
        $full = str_starts_with($ruleValue, 'FULL:');
        if ($full) $ruleValue = substr($ruleValue, strlen('FULL:'));

        $values = explode(',', $ruleValue);
        foreach ($values as $val) {
            $val = trim($val);
            if (preg_match('/^(>=|<=|>|<)(\d+)$/', $val, $m)) {
                $op = $m[1];
                $num = (int)$m[2];
                $actualNum = (int)$actualValue;
                $matched = false;
                switch ($op) {
                    case '>': $matched = $actualNum > $num; break;
                    case '<': $matched = $actualNum < $num; break;
                    case '>=': $matched = $actualNum >= $num; break;
                    case '<=': $matched = $actualNum <= $num; break;
                }
                if ($matched) return !$not;
            } elseif ($exact || $full) {
                if ($actualValue === $val) return !$not;
            } else {
                if (strpos($actualValue, strtolower($val)) !== false) return !$not;
            }
        }
        return $not;
    }
}
