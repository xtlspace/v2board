<?php

namespace App\Services;

use App\Utils\IpNormalizer;
use Illuminate\Support\Facades\Cache;

class NodeIpRegistry
{
    private const CACHE_KEY = 'ALIVE_NODE_REPORTED_IPS';
    private const LOCK_KEY = 'ALIVE_NODE_REPORTED_IPS_LOCK';
    private const REALM_SYNC_INTERVAL = 120;
    private const REALM_HASH_PREFIX = 'ALIVE_NODE_REALM_HASH_';
    private const REALM_AT_PREFIX = 'ALIVE_NODE_REALM_SYNCED_AT_';
    private const MAX_IPS_PER_NODE = 16;
    private const IP_TTL = 300;
    private const NODE_TTL = 3600;

    /**
     * 记录节点 IP，按 IP 合并；IP 5 分钟过期，节点 1 小时过期。
     *
     * @param int $nodeId
     * @param string[] $ips
     */
    public function report(int $nodeId, array $ips): void
    {
        if ($nodeId <= 0) {
            return;
        }

        $now = time();
        $normalized = [];
        foreach ($ips as $ip) {
            $canonical = IpNormalizer::canonical($ip);
            if ($canonical !== null) {
                $normalized[$canonical] = $now;
            }
        }
        if (empty($normalized)) {
            return;
        }

        $write = function () use ($nodeId, $normalized, $now) {
            $registry = $this->load();
            $key = (string) $nodeId;
            $entry = $registry[$key] ?? [];
            $merged = is_array($entry['ips'] ?? null) ? $entry['ips'] : [];
            foreach ($normalized as $ip => $ts) {
                $merged[$ip] = $ts;
            }
            foreach ($merged as $ip => $ts) {
                $ts = (int) $ts;
                if ($ts <= 0 || $now - $ts > self::IP_TTL) {
                    unset($merged[$ip]);
                }
            }
            if (empty($merged)) {
                unset($registry[$key]);
            } else {
                $registry[$key] = [
                    'ips' => $merged,
                    'ts' => $now,
                ];
            }
            $this->prune($registry, $now);
            Cache::put(self::CACHE_KEY, $registry, self::NODE_TTL);
        };

        $this->withLock($write);
    }

    public function forget(int $nodeId): void
    {
        if ($nodeId <= 0) {
            return;
        }

        $write = function () use ($nodeId) {
            $registry = $this->load();
            if (!isset($registry[(string) $nodeId])) {
                return;
            }
            unset($registry[(string) $nodeId]);
            Cache::put(self::CACHE_KEY, $registry, self::NODE_TTL);
        };

        $this->withLock($write);
    }

    /**
     * 将节点的真实地址（IP 或域名）加入缓存。
     *
     * 每个节点最多每 2 分钟解析一次，避免频繁 DNS。
     */
    public function reportHost(int $nodeId, string $host): void
    {
        if ($nodeId <= 0) {
            return;
        }
        $host = trim($host);
        if ($host === '') {
            return;
        }

        $now = time();
        $hashKey = self::REALM_HASH_PREFIX . $nodeId;
        $atKey = self::REALM_AT_PREFIX . $nodeId;
        $hash = md5($host);
        $lastHash = (string) Cache::get($hashKey, '');
        $lastAt = (int) Cache::get($atKey, 0);
        if ($lastHash === $hash && $now - $lastAt < self::REALM_SYNC_INTERVAL) {
            return;
        }
        Cache::put($hashKey, $hash, self::REALM_SYNC_INTERVAL * 2);
        Cache::put($atKey, $now, self::REALM_SYNC_INTERVAL * 2);

        $ips = $this->resolveHost($host);
        if (empty($ips)) {
            return;
        }
        $this->report($nodeId, array_slice($ips, 0, self::MAX_IPS_PER_NODE));
    }

    /**
     * @return string[]
     */
    private function resolveHost(string $target): array
    {
        $canonical = IpNormalizer::canonical($target);
        if ($canonical !== null) {
            return [$canonical];
        }

        $domain = rtrim(trim($target), '.');
        if ($domain === '' || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return [];
        }
        if (!function_exists('dns_get_record')) {
            return [];
        }

        $records = @dns_get_record($domain, DNS_A | DNS_AAAA);
        if (!is_array($records)) {
            return [];
        }

        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!is_string($ip)) {
                continue;
            }
            $canonical = IpNormalizer::canonical($ip);
            if ($canonical !== null) {
                $ips[$canonical] = true;
            }
        }
        return array_keys($ips);
    }

    /**
     * @return string[]
     */
    public function allIps(): array
    {
        $ips = [];
        foreach ($this->freshRegistry() as $entry) {
            foreach (array_keys($entry['ips']) as $ip) {
                $ips[$ip] = true;
            }
        }
        return array_keys($ips);
    }

    /**
     * 同一 IP 被多个节点上报时，取最小节点 ID。
     *
     * @return array<string, int>
     */
    public function ipNodeMap(): array
    {
        $map = [];
        foreach ($this->freshRegistry() as $nodeId => $entry) {
            $id = (int) $nodeId;
            if ($id <= 0) {
                continue;
            }
            foreach (array_keys($entry['ips']) as $ip) {
                $canonical = IpNormalizer::canonical($ip);
                if ($canonical === null) {
                    continue;
                }
                if (!isset($map[$canonical]) || $id < $map[$canonical]) {
                    $map[$canonical] = $id;
                }
            }
        }
        return $map;
    }

    /**
     * @return array<string, true>
     */
    public function packedIpSet(): array
    {
        $set = [];
        foreach ($this->allIps() as $ip) {
            $packed = IpNormalizer::packed($ip);
            if ($packed !== null) {
                $set[$packed] = true;
            }
        }
        return $set;
    }

    private function load(): array
    {
        $registry = Cache::get(self::CACHE_KEY, []);
        return is_array($registry) ? $registry : [];
    }

    /**
     * @return array<string, array{ips: array<string,int>, ts: int}>
     */
    private function freshRegistry(): array
    {
        $now = time();
        $fresh = [];
        foreach ($this->load() as $nodeId => $entry) {
            if (!is_array($entry) || !is_array($entry['ips'] ?? null)) {
                continue;
            }
            $ts = (int) ($entry['ts'] ?? 0);
            if ($ts <= 0 || $now - $ts > self::NODE_TTL) {
                continue;
            }
            $ips = [];
            foreach ($entry['ips'] as $ip => $ipTs) {
                $ipTs = (int) $ipTs;
                if ($ipTs > 0 && $now - $ipTs <= self::IP_TTL) {
                    $ips[$ip] = $ipTs;
                }
            }
            if (!empty($ips)) {
                $fresh[(string) $nodeId] = ['ips' => $ips, 'ts' => $ts];
            }
        }
        return $fresh;
    }

    private function prune(array &$registry, int $now): void
    {
        foreach ($registry as $nodeId => $entry) {
            if (!is_array($entry) || !is_array($entry['ips'] ?? null)) {
                unset($registry[$nodeId]);
                continue;
            }
            $ts = (int) ($entry['ts'] ?? 0);
            if ($ts <= 0 || $now - $ts > self::NODE_TTL) {
                unset($registry[$nodeId]);
                continue;
            }
            foreach ($entry['ips'] as $ip => $ipTs) {
                $ipTs = (int) $ipTs;
                if ($ipTs <= 0 || $now - $ipTs > self::IP_TTL) {
                    unset($entry['ips'][$ip]);
                }
            }
            if (empty($entry['ips'])) {
                unset($registry[$nodeId]);
            } else {
                $registry[$nodeId] = $entry;
            }
        }
    }

    private function withLock(callable $callback): void
    {
        try {
            Cache::lock(self::LOCK_KEY, 3)->block(2, $callback);
        } catch (\Throwable $e) {
            $callback();
        }
    }
}
