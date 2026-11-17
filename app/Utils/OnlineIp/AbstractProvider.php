<?php

namespace App\Utils\OnlineIp;

abstract class AbstractProvider implements OnlineIpProvider
{
    protected function pick(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $value = trim((string) $data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    protected function result(string $country, string $region, string $city, string $isp): ?array
    {
        if ($country === '' && $region === '' && $city === '' && $isp === '') {
            return null;
        }
        return [
            'country' => $country,
            'region' => $region,
            'city' => $city,
            'isp' => $isp,
        ];
    }
}
