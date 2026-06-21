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
        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $subLog->ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $subLog->ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $subLog->ip = $_SERVER['REMOTE_ADDR'] ?? $request->ip();
        }
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
        
        // account not expired and is not banned.
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            $servers = $this->applySubRules($servers, $subLog, $location);
            if($flag) {
                if (!strpos($flag, 'sing')) {
                    $this->setSubscribeInfoToServers($servers, $user);
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
                if ($rule->original_host && $rule->original_host !== $server['host']) continue;
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
        if ($exact) {
            return $not ? $actualValue !== $ruleValue : $actualValue === $ruleValue;
        } else {
            $contains = strpos($actualValue, strtolower($ruleValue)) !== false;
            return $not ? !$contains : $contains;
        }
    }
}
