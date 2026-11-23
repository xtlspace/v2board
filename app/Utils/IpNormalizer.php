<?php

namespace App\Utils;

class IpNormalizer
{
    /**
     * 统一 IP 表示：
     * - 去掉方括号、端口、IPv6 zone
     * - IPv4-mapped IPv6 转 IPv4
     * - IPv6 统一截断到 /64
     *
     * @return string|null 4 或 16 字节的 packed 值
     */
    public static function packed($ip): ?string
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
        if ($packed === false) {
            return null;
        }
        if (strlen($packed) === 16) {
            // IPv6 统一按 /64 处理，避免同一设备的不同后缀产生多条记录
            $packed = substr($packed, 0, 8) . str_repeat("\0", 8);
        }
        return $packed;
    }

    /**
     * 返回规范化后的字符串表示，IPv6 为 /64 网段地址（如 2001:db8:1:2::）。
     */
    public static function canonical($ip): ?string
    {
        $packed = self::packed($ip);
        if ($packed === null) {
            return null;
        }
        $canonical = @inet_ntop($packed);
        return $canonical === false ? null : $canonical;
    }

    /**
     * 判断是否为局域网 / 保留地址。
     */
    public static function isLan($ip): bool
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return false;
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
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return false;
            }
            // 只有 2000::/3 视为公网 IPv6
            return (ord($packed[0]) & 0xE0) !== 0x20;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long !== false && (($long & 0xFFC00000) === 0x64400000)) {
                // 100.64.0.0/10 CGNAT
                return true;
            }
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
