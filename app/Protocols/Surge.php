<?php

namespace App\Protocols;

use App\Utils\Helper;

class Surge
{
    public $flag = 'surge';
    public $flags = ['surfboard'];
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;

        $appName = config('v2board.app_name', 'V2Board');
        header("content-disposition:attachment;filename*=UTF-8''".rawurlencode($appName).".conf");

        $proxies = '';
        $proxyGroup = '';

        foreach ($servers as $item) {
            if (($item['type'] ?? null) === 'v2node' && isset($item['protocol'])) {
                $item['type'] = $item['protocol'];
            }
            if ($item['type'] === 'shadowsocks') {
                // [Proxy]
                $proxies .= self::buildShadowsocks($user['uuid'], $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }elseif ($item['type'] === 'vmess') {
                // [Proxy]
                $proxies .= self::buildVmess($user['uuid'], $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }elseif ($item['type'] === 'trojan') {
                // [Proxy]
                $proxies .= self::buildTrojan($user['uuid'], $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }elseif ($item['type'] === 'tuic') {
                // [Proxy]
                $proxies .= self::buildTuic($user['uuid'], $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }elseif ($item['type'] === 'hysteria2' || ($item['type'] === 'hysteria' && (int)($item['version'] ?? 0) === 2)) {
                // [Proxy]
                $proxies .= self::buildHysteria2($user['uuid'], $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }elseif ($item['type'] === 'anytls') {
                // [Proxy]
                $proxies .= self::buildAnyTLS($user['uuid'], $item);
                // [Proxy Group]
                $proxyGroup .= $item['name'] . ', ';
            }
        }

        $defaultConfig = base_path() . '/resources/rules/default.surge.conf';
        $customConfig = base_path() . '/resources/rules/custom.surge.conf';
        if (\File::exists($customConfig)) {
            $config = file_get_contents("$customConfig");
        } else {
            $config = file_get_contents("$defaultConfig");
        }

        // Subscription link
        $subsURL = Helper::getSubscribeUrl($user['token']);
        $subsDomain = $_SERVER['HTTP_HOST'];

        $config = str_replace('$subs_link', $subsURL, $config);
        $config = str_replace('$subs_domain', $subsDomain, $config);
        $config = str_replace('$proxies', $proxies, $config);
        $config = str_replace('$proxy_group', rtrim($proxyGroup, ', '), $config);

        $upload = round($user['u'] / (1024*1024*1024), 2);
        $download = round($user['d'] / (1024*1024*1024), 2);
        $useTraffic = $upload + $download;
        $totalTraffic = round($user['transfer_enable'] / (1024*1024*1024), 2);
        $expireDate = $user['expired_at'] === NULL ? '长期有效' : date('Y-m-d H:i:s', $user['expired_at']);
        $subscribeInfo = "title={$appName}订阅信息, content=上传流量：{$upload}GB\\n下载流量：{$download}GB\\n剩余流量：{$useTraffic}GB\\n套餐流量：{$totalTraffic}GB\\n到期时间：{$expireDate}";
        $config = str_replace('$subscribe_info', $subscribeInfo, $config);

        return $config;
    }

    // https://manual.nssurge.com/policies/shadowsocks.html
    public static function buildShadowsocks($password, $server)
    {
        if ($server['cipher'] === '2022-blake3-aes-128-gcm') {
            $serverKey = Helper::getServerKey($server['created_at'], 16);
            $userKey = Helper::uuidToBase64($password, 16);
            $password = "{$serverKey}:{$userKey}";
        } elseif ($server['cipher'] === '2022-blake3-aes-256-gcm') {
            $serverKey = Helper::getServerKey($server['created_at'], 32);
            $userKey = Helper::uuidToBase64($password, 32);
            $password = "{$serverKey}:{$userKey}";
        }
        $config = [
            "{$server['name']}=ss",
            "{$server['host']}",
            "{$server['port']}",
            "encrypt-method={$server['cipher']}",
            "password={$password}",
        ];

        if (isset($server['obfs']) && $server['obfs'] === 'http') {
            $config[] = "obfs={$server['obfs']}";
            if (isset($server['obfs-host']) && !empty($server['obfs-host'])) {
                $config[] = "obfs-host={$server['obfs-host']}";
            }
            if (isset($server['obfs-path'])) {
                $config[] = "obfs-uri={$server['obfs-path']}";
            }
        }
        $config[] = 'tfo=true';
        $config[] = 'udp-relay=true';
        $uri = implode(',', $config);
        $uri .= "\r\n";

        return $uri;
    }

    // https://manual.nssurge.com/policies/vmess.html
    public static function buildVmess($uuid, $server)
    {
        $config = [
            "{$server['name']}=vmess",
            "{$server['host']}",
            "{$server['port']}",
            "username={$uuid}",
            "vmess-aead=true",
            'tfo=true'
        ];

        if (!empty($server['tls'])) {
            $config[] = 'tls=true';
            self::appendTlsParams($config, $server);
        }
        if (($server['network'] ?? null) === 'ws') {
            $config[] = 'ws=true';
            $wsSettings = self::getNetworkSettings($server);
            if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                $config[] = "ws-path={$wsSettings['path']}";
            if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                $config[] = "ws-headers=Host:{$wsSettings['headers']['Host']}";
            if (isset($wsSettings['security']) && !empty($wsSettings['security']))
                $config[] = "encrypt-method={$wsSettings['security']}";
        }

        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    // https://manual.nssurge.com/policies/trojan.html
    public static function buildTrojan($password, $server)
    {
        $config = [
            "{$server['name']}=trojan",
            "{$server['host']}",
            "{$server['port']}",
            "password={$password}",
            'tfo=true'
        ];
        self::appendTlsParams($config, $server);

        if (($server['network'] ?? null) === 'ws') {
            $config[] = 'ws=true';
            $wsSettings = self::getNetworkSettings($server);
            if (isset($wsSettings['path']) && !empty($wsSettings['path']))
                $config[] = "ws-path={$wsSettings['path']}";
            if (isset($wsSettings['headers']['Host']) && !empty($wsSettings['headers']['Host']))
                $config[] = "ws-headers=Host:{$wsSettings['headers']['Host']}";
        }
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    // https://manual.nssurge.com/policies/tuic.html
    public static function buildTuic($password, $server)
    {
        $config = [
            "{$server['name']}=tuic-v5",
            "{$server['host']}",
            self::firstPort($server['port']),
            "uuid={$password}",
            "password={$password}",
            'alpn=h3',
            'tfo=true'
        ];
        self::appendTlsParams($config, $server);
        self::appendPortHopping($config, $server['port']);
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    // https://manual.nssurge.com/policies/hysteria2.html
    public static function buildHysteria2($password, $server)
    {
        $config = [
            "{$server['name']}=hysteria2",
            "{$server['host']}",
            self::firstPort($server['port']),
            "password={$password}"
        ];
        if (!empty($server['up_mbps'])) {
            $config[] = "download-bandwidth={$server['up_mbps']}";
        }
        self::appendTlsParams($config, $server);
        if (!empty($server['obfs']) && !empty($server['obfs_password'])) {
            $obfs = strtolower((string)$server['obfs']);
            $key = $obfs === 'gecko' ? 'gecko-password' : 'salamander-password';
            $config[] = "{$key}={$server['obfs_password']}";
        }
        self::appendPortHopping($config, $server['port']);
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    // https://manual.nssurge.com/policies/anytls.html
    public static function buildAnyTLS($password, $server)
    {
        $config = [
            "{$server['name']}=anytls",
            "{$server['host']}",
            "{$server['port']}",
            "password={$password}",
            'reuse=true',
            'tfo=true'
        ];
        self::appendTlsParams($config, $server);
        $config = array_filter($config);
        $uri = implode(',', $config);
        $uri .= "\r\n";
        return $uri;
    }

    private static function getTlsSettings($server)
    {
        return $server['tls_settings'] ?? ($server['tlsSettings'] ?? []);
    }

    private static function getNetworkSettings($server)
    {
        return $server['network_settings'] ?? ($server['networkSettings'] ?? []);
    }

    private static function firstPort($port)
    {
        $parts = explode(',', $port);
        $firstPart = $parts[0];
        if (strpos($firstPart, '-') !== false) {
            return explode('-', $firstPart)[0];
        }
        return $firstPart;
    }

    private static function appendPortHopping(&$config, $port)
    {
        if (strpos($port, ',') !== false || strpos($port, '-') !== false) {
            $config[] = 'port-hopping=' . str_replace(',', ';', $port);
        }
    }

    private static function appendTlsParams(&$config, $server)
    {
        $tlsSettings = self::getTlsSettings($server);
        $serverName = $server['server_name'] ?? ($tlsSettings['server_name'] ?? ($tlsSettings['serverName'] ?? ''));
        if (!empty($server['disable_sni'])) {
            $config[] = 'sni=off';
        } elseif (!empty($serverName)) {
            $config[] = "sni={$serverName}";
        }

        $allowInsecure = (int)($server['insecure'] ?? ($tlsSettings['allow_insecure'] ?? ($tlsSettings['allowInsecure'] ?? 0)));
        if ($allowInsecure === 1) {
            $config[] = 'skip-cert-verify=true';
        }
    }
}
