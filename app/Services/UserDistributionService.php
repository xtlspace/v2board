<?php

namespace App\Services;

use App\Utils\IPLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserDistributionService
{
    private const MAINLAND_PROVINCES = [
        '北京', '天津', '河北', '山西', '内蒙古', '辽宁', '吉林', '黑龙江',
        '上海', '江苏', '浙江', '安徽', '福建', '江西', '山东', '河南',
        '湖北', '湖南', '广东', '广西', '海南', '重庆', '四川', '贵州',
        '云南', '西藏', '陕西', '甘肃', '青海', '宁夏', '新疆',
    ];

    private const PROVINCE_ALIASES = [
        '内蒙古自治区' => '内蒙古',
        '广西壮族自治区' => '广西',
        '西藏自治区' => '西藏',
        '宁夏回族自治区' => '宁夏',
        '新疆维吾尔自治区' => '新疆',
    ];

    public function record(array $data): void
    {
        $now = time();
        $rows = [];
        $seen = [];
        $location = new IPLocation();

        foreach ($data as $userId => $ips) {
            if (!is_numeric($userId) || !is_array($ips)) {
                continue;
            }
            $userId = (int) $userId;
            foreach ($ips as $ipNodeId) {
                $ip = $this->normalizeIp($ipNodeId);
                if ($ip === null || isset($seen[$userId][$ip])) {
                    continue;
                }
                $seen[$userId][$ip] = true;

                $info = $this->resolveLocation($ip, $location);
                if ($info === null) {
                    continue;
                }

                $rows[] = [
                    'user_id' => $userId,
                    'ip' => $ip,
                    'country' => $info['country'],
                    'province' => $info['province'],
                    'city' => $info['city'],
                    'isp' => $info['isp'],
                    'provider' => $info['provider'],
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('v2_alive_ip_history')->upsert(
                $chunk,
                ['user_id', 'ip'],
                ['country', 'province', 'city', 'isp', 'provider', 'last_seen_at', 'updated_at']
            );
        }

        $touched = [];
        foreach ($data as $userId => $ips) {
            if (is_numeric($userId) && is_array($ips) && !empty($ips)) {
                $touched[] = (int) $userId;
            }
        }
        if (!empty($touched)) {
            RiskQueue::markMany($touched);
        }
    }

    private function normalizeIp($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $ip = explode('_', (string) $value)[0];
        $ip = trim($ip);
        if (preg_match('/^\[(.+)\]$/', $ip, $matches)) {
            $ip = $matches[1];
        }
        $ip = preg_replace('/%.*$/', '', $ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        return $ip;
    }

    private function resolveLocation(string $ip, IPLocation $location): ?array
    {
        $cacheKey = 'alive_ip_location2|' . self::locationEpoch() . '|' . md5(IPLocation::subnetKey($ip));
        $cached = Cache::get($cacheKey, '__miss__');
        if ($cached !== '__miss__') {
            return is_array($cached) ? $cached : null;
        }

        $info = $location->find($ip);
        if (!$info) {
            Cache::put($cacheKey, false, 604800);
            return null;
        }

        $country = trim((string) ($info['country'] ?? ''));
        $region = trim((string) ($info['region'] ?? ''));
        $city = trim((string) ($info['city'] ?? ''));
        $isp = trim((string) ($info['isp'] ?? ''));

        // 港澳台统一按地区名记录
        $special = in_array($region, ['香港', '澳门', '台湾'], true);
        if (!$special) {
            foreach (['香港', '澳门', '台湾'] as $name) {
                if ($country !== '' && strpos($country, $name) !== false) {
                    $special = true;
                    $region = $name;
                    break;
                }
            }
        }

        $province = $special ? $region : self::normalizeProvince($region);

        if ($country === '' && $province === null) {
            Cache::put($cacheKey, false, 604800);
            return null;
        }
        if ($special || $province !== null) {
            $country = '中国';
        }

        $result = [
            'country' => $country,
            'province' => $province,
            // 非大陆只保留国家和运营商
            'city' => $province !== null ? $city : '',
            'isp' => $isp,
            'provider' => self::classifyProvider($isp),
            'mainland' => $province !== null && !$special,
        ];
        Cache::put($cacheKey, $result, 604800);

        return $result;
    }

    /**
     * 归属地缓存版本号，重建缓存后递增使旧缓存立即失效
     */
    public static function locationEpoch(): int
    {
        return (int) Cache::get('alive_ip_location_epoch', 1);
    }

    public static function bumpLocationEpoch(): void
    {
        Cache::forever('alive_ip_location_epoch', self::locationEpoch() + 1);
    }

    public static function normalizeProvince(string $region): ?string
    {
        $region = trim($region);
        if ($region === '') {
            return null;
        }
        if (isset(self::PROVINCE_ALIASES[$region])) {
            $region = self::PROVINCE_ALIASES[$region];
        } else {
            $region = preg_replace('/(省|市)$/u', '', $region);
        }

        return in_array($region, self::MAINLAND_PROVINCES, true) ? $region : null;
    }

    public static function classifyProvider(string $isp): string
    {
        if (strpos($isp, '电信') !== false) {
            return 'telecom';
        }
        if (strpos($isp, '联通') !== false) {
            return 'unicom';
        }
        if (strpos($isp, '移动') !== false) {
            return 'mobile';
        }
        if (preg_match('/(广电|有线|歌华|天威|珠江数码|东方有线)/u', $isp)) {
            return 'cable';
        }
        if (preg_match('/(教育网|CERNET|校园网)/iu', $isp)) {
            return 'edu';
        }
        return 'other';
    }
}
