<?php

namespace App\Utils\OnlineIp;

class Ip9Provider extends AbstractProvider
{
    public function key(): string
    {
        return 'ip9';
    }

    public function name(): string
    {
        return 'ip9.com.cn';
    }

    public function url(): string
    {
        return 'https://ip9.com.cn/get?ip={ip}';
    }

    public function parse(array $json): ?array
    {
        if (array_key_exists('ret', $json) && (string) $json['ret'] !== '200') {
            return null;
        }
        $data = $json['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        return $this->result(
            $this->pick($data, ['country', 'country_name']),
            $this->pick($data, ['prov', 'province', 'region', 'state']),
            $this->pick($data, ['city', 'city_name']),
            $this->pick($data, ['isp', 'operator', 'carrier'])
        );
    }
}
