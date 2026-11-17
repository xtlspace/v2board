<?php

namespace App\Utils;

class IPLocation
{
    private $searcher = null;

    public function __construct($v4Path = null, $v6Path = null)
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

        $this->searcher = new \Ip2Region('file', $v4Path, $v6Path);
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

    public function find($ip)
    {
        try {
            $result = $this->searcher->getIpInfo($ip);
            if (!$result) return null;
            return [
                'country' => $result['country'] ?? '',
                'region'  => $result['province'] ?? '',
                'city'    => $result['city'] ?? '',
                'isp'     => $result['isp'] ?? '',
            ];
        } catch (\Exception $e) {
            return null;
        }
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
