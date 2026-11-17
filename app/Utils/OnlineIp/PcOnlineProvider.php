<?php

namespace App\Utils\OnlineIp;

use App\Services\UserDistributionService;

class PcOnlineProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'pconline';
    }

    public function name(): string
    {
        return 'PCOnline';
    }

    public function url(): string
    {
        return 'http://whois.pconline.com.cn/ipJson.jsp?ip={ip}&json=true';
    }

    public function parse(array $json): ?array
    {
        if (!empty($json['err'])) {
            return null;
        }

        $province = $this->pick($json, ['pro']);
        $city = $this->pick($json, ['city']);
        $addr = $this->pick($json, ['addr']);

        $country = '';
        if ($province !== '') {
            if (UserDistributionService::normalizeProvince($province) !== null) {
                $country = '中国';
            } else {
                $country = $province;
                $province = '';
            }
        }

        // addr 形如「北京市 华电集团」「美国 加利福尼亚州」，去掉省市前缀得到运营商
        $isp = $addr;
        foreach (array_filter([$this->pick($json, ['pro']), $city, $country]) as $prefix) {
            if ($isp !== '' && strpos($isp, $prefix) === 0) {
                $isp = trim(mb_substr($isp, mb_strlen($prefix)));
            }
        }

        return $this->result($country, $province, $city, trim($isp));
    }
}
