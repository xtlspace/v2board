<?php

namespace App\Utils;

use App\Utils\OnlineIp\OnlineIpProvider;
use App\Utils\OnlineIp\ProviderRegistry;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class IPLocation
{
    private $searcher = null;
    private $searcherError = null;
    private $forceSource = null;
    private $forceProviders = null;
    private $lastError = null;

    public function __construct($v4Path = null, $v6Path = null)
    {
        $localRequired = $this->configSource(4) === 'local' || $this->configSource(6) === 'local';
        try {
            $this->searcher = $this->createSearcher($v4Path, $v6Path);
        } catch (\Throwable $e) {
            $this->searcher = null;
            $this->searcherError = $e->getMessage();
            if ($localRequired) {
                throw $e;
            }
        }
    }

    /**
     * 临时指定解析来源，不影响全局配置
     *
     * @param string $source local|online
     * @param array|null $providers 在线模式下使用的接口 key 列表
     */
    public function withSource(string $source, array $providers = null): self
    {
        $this->forceSource = $source === 'online' ? 'online' : 'local';
        $this->forceProviders = $providers;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * 缓存用的网段标识：IPv4 按 /24，其余按原值
     */
    public static function subnetKey(string $ip): string
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
        }
        return $ip;
    }

    /**
     * 清理某个 IP 所在网段的在线接口缓存
     *
     * @param array|null $providerKeys 不传则使用当前启用的接口列表
     */
    public static function forgetOnlineCache(string $ip, array $providerKeys = null): void
    {
        $providerKeys = $providerKeys ?? ProviderRegistry::enabledKeys();
        $cacheKey = 'ip_online_lookup|' . md5(implode('|', $providerKeys)) . '|' . md5(self::subnetKey($ip));
        try {
            Cache::forget($cacheKey);
        } catch (\Throwable $e) {
        }
    }

    public function find($ip)
    {
        $this->lastError = null;
        $ip = trim((string) $ip);
        if ($ip === '') {
            $this->lastError = 'IP 为空';
            return null;
        }

        $source = $this->sourceFor($ip);
        if ($source === 'online') {
            $result = $this->findOnline($ip);
            if ($result !== null) {
                return $result;
            }
            // 强制在线（单用户重新定位）时失败不回退
            if ($this->forceSource === 'online') {
                return null;
            }
        }

        return $this->findLocal($ip);
    }

    private function sourceFor(string $ip): string
    {
        if ($this->forceSource !== null) {
            return $this->forceSource;
        }
        $version = strpos($ip, ':') !== false ? 6 : 4;
        return $this->configSource($version);
    }

    private function configSource(int $version): string
    {
        $key = $version === 6 ? 'ip_lookup_source_v6' : 'ip_lookup_source_v4';
        return config("v2board.$key", 'local') === 'online' ? 'online' : 'local';
    }

    /**
     * @return OnlineIpProvider[]
     */
    private function onlineProviders(): array
    {
        if ($this->forceProviders !== null) {
            $providers = [];
            foreach ($this->forceProviders as $key) {
                $provider = ProviderRegistry::find((string) $key);
                if ($provider !== null) {
                    $providers[] = $provider;
                }
            }
            return $providers;
        }

        return ProviderRegistry::enabled();
    }

    private function findOnline(string $ip): ?array
    {
        $providers = $this->onlineProviders();
        if (empty($providers)) {
            $this->lastError = '未配置在线查询接口';
            return null;
        }

        $providerKeys = array_map(function (OnlineIpProvider $provider) {
            return $provider->key();
        }, $providers);

        $cacheKey = 'ip_online_lookup|' . md5(implode('|', $providerKeys)) . '|' . md5(self::subnetKey($ip));
        $cached = Cache::get($cacheKey, '__miss__');
        if ($cached !== '__miss__') {
            if (is_array($cached)) {
                return $cached;
            }
            $this->lastError = '在线接口无结果（缓存）';
            return null;
        }

        $errors = [];
        foreach ($providers as $provider) {
            $url = $provider->url();
            if (strpos($url, '{ip}') !== false) {
                $endpoint = str_replace('{ip}', rawurlencode($ip), $url);
            } else {
                $endpoint = rtrim($url, '/') . (strpos($url, '?') === false ? '?ip=' : '&ip=') . rawurlencode($ip);
            }
            try {
                $client = new Client([
                    'timeout' => 3,
                    'connect_timeout' => 2,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                        'Accept' => 'application/json',
                    ],
                ]);
                $response = $client->get($endpoint);
                $json = json_decode((string) $response->getBody(), true);
                $parsed = is_array($json) ? $provider->parse($json) : null;
                if ($parsed !== null) {
                    Cache::put($cacheKey, $parsed, 7 * 86400);
                    return $parsed;
                }
                $errors[] = $provider->name() . ' 无有效结果';
            } catch (\Throwable $e) {
                $errors[] = $provider->name() . ' ' . $e->getMessage();
            }
        }

        Cache::put($cacheKey, false, 600);
        $this->lastError = '在线接口失败：' . implode('；', array_slice($errors, 0, 3));
        return null;
    }

    private function findLocal(string $ip): ?array
    {
        if ($this->searcher === null) {
            $this->lastError = $this->searcherError ?: '本地 IP 库不可用';
            return null;
        }
        try {
            $result = $this->searcher->getIpInfo($ip);
            if (!$result) {
                $this->lastError = '本地 IP 库无结果';
                return null;
            }
            return [
                'country' => $result['country'] ?? '',
                'region'  => $result['province'] ?? '',
                'city'    => $result['city'] ?? '',
                'isp'     => $result['isp'] ?? '',
            ];
        } catch (\Throwable $e) {
            $this->lastError = '本地 IP 库解析失败：' . $e->getMessage();
            return null;
        }
    }

    private function createSearcher($v4Path, $v6Path)
    {
        if ($v4Path === null) {
            $configured = trim((string) config('v2board.ip_db_v4_path', ''));
            $v4Path = $configured !== '' ? self::resolvePath($configured) : self::defaultPath('ipdata.xdb');
        } else {
            $v4Path = self::resolvePath((string) $v4Path);
        }
        if ($v4Path === '' || !is_file($v4Path) || !is_readable($v4Path)) {
            throw new \RuntimeException("IPv4 IP 库不可读: {$v4Path}");
        }

        if ($v6Path === null) {
            $configured = trim((string) config('v2board.ip_db_v6_path', ''));
            if ($configured !== '') {
                $v6Path = self::resolvePath($configured);
            } else {
                $v6Path = self::defaultPath('ipdata6.xdb', false);
            }
        } else {
            $v6Path = trim((string) $v6Path) === '' ? null : self::resolvePath((string) $v6Path);
        }
        if ($v6Path !== null && (!is_file($v6Path) || !is_readable($v6Path))) {
            throw new \RuntimeException("IPv6 IP 库不可读: {$v6Path}");
        }

        return new \Ip2Region('file', $v4Path, $v6Path);
    }

    /**
     * 相对路径按项目根目录解析，绝对路径原样返回
     */
    public static function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^(?:[a-zA-Z]:[\\\\/]|/|\\\\\\\\)#', $path)) {
            return $path;
        }
        return base_path($path);
    }

    private static function defaultPath(string $file, bool $required = true): ?string
    {
        $path = base_path($file);
        if (!is_file($path)) {
            $path = storage_path('app/' . $file);
        }
        if (!is_file($path)) {
            if ($required) {
                throw new \RuntimeException("IP 库不存在: {$path}");
            }
            return null;
        }
        return $path;
    }

    public function close()
    {
        $this->searcher = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
