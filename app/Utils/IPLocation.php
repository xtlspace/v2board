<?php

namespace App\Utils;

class IPLocation
{
    private $searcher = null;

    public function __construct($v4Path = null, $v6Path = null)
    {
        if ($v4Path === null) {
            $v4Path = base_path('ipdata.xdb');
            if (!file_exists($v4Path)) {
                $v4Path = storage_path('app/ipdata.xdb');
            }
        }
        if (!file_exists($v4Path)) {
            throw new \RuntimeException("ipdata.xdb not found at: {$v4Path}");
        }

        if ($v6Path === null) {
            $v6Path = base_path('ipdata6.xdb');
            if (!file_exists($v6Path)) {
                $v6Path = storage_path('app/ipdata6.xdb');
            }
        }

        $this->searcher = new \Ip2Region('file', $v4Path, file_exists($v6Path) ? $v6Path : null);
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
