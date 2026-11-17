<?php

namespace App\Utils\OnlineIp;

class ProviderRegistry
{
    /**
     * 所有可用接口，新增接口只需在这里加一行
     *
     * @return OnlineIpProvider[]
     */
    public static function all(): array
    {
        return [
            new BilibiliProvider(),
            new Ip9Provider(),
            new PcOnlineProvider(),
        ];
    }

    /**
     * @return string[]
     */
    public static function keys(): array
    {
        return array_map(function (OnlineIpProvider $provider) {
            return $provider->key();
        }, self::all());
    }

    public static function find(string $key): ?OnlineIpProvider
    {
        foreach (self::all() as $provider) {
            if ($provider->key() === $key) {
                return $provider;
            }
        }
        return null;
    }

    /**
     * 当前启用的接口，按配置顺序
     *
     * @return OnlineIpProvider[]
     */
    public static function enabled(): array
    {
        $configured = config('v2board.ip_lookup_providers', []);
        if (is_string($configured)) {
            $decoded = json_decode($configured, true);
            $configured = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($configured)) {
            $configured = [];
        }

        $list = [];
        foreach ($configured as $key) {
            if (!is_string($key)) {
                continue;
            }
            $provider = self::find(trim($key));
            if ($provider !== null) {
                $list[$provider->key()] = $provider;
            }
        }

        if (empty($list)) {
            return self::all();
        }

        return array_values($list);
    }

    /**
     * 已启用接口的 key 列表
     *
     * @return string[]
     */
    public static function enabledKeys(): array
    {
        return array_map(function (OnlineIpProvider $provider) {
            return $provider->key();
        }, self::enabled());
    }

    /**
     * 供设置页展示的接口清单
     */
    public static function options(): array
    {
        return array_map(function (OnlineIpProvider $provider) {
            return [
                'key' => $provider->key(),
                'name' => $provider->name(),
                'url' => $provider->url(),
            ];
        }, self::all());
    }
}
