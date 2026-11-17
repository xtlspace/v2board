<?php

namespace App\Utils\OnlineIp;

interface OnlineIpProvider
{
    /**
     * 唯一标识，用于配置保存
     */
    public function key(): string;

    /**
     * 显示名称
     */
    public function name(): string;

    /**
     * 接口地址，必须包含 {ip} 占位符
     */
    public function url(): string;

    /**
     * 解析接口返回的 JSON，返回 country/region/city/isp 或 null
     */
    public function parse(array $json): ?array;
}
