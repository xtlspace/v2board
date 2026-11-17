<?php

namespace App\Utils\OnlineIp;

class BilibiliProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'bilibili';
    }

    public function name(): string
    {
        return 'Bilibili';
    }

    public function url(): string
    {
        return 'https://api.live.bilibili.com/ip_service/v1/ip_service/get_ip_addr?ip={ip}';
    }

    public function parse(array $json): ?array
    {
        if (array_key_exists('code', $json) && (string) $json['code'] !== '0') {
            return null;
        }
        $data = $json['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        return $this->result(
            $this->pick($data, ['country', 'country_name', 'nation']),
            $this->pick($data, ['province', 'prov', 'region', 'state']),
            $this->pick($data, ['city', 'city_name']),
            $this->pick($data, ['isp', 'operator', 'carrier'])
        );
    }
}
