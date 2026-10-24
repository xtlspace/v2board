<?php

namespace App\Utils;

use Symfony\Component\Process\Process;

class ConfigCache
{
    /**
     * 重建配置缓存
     *
     * 严禁在 HTTP 请求里直接调用 Artisan::call('config:cache')：
     * config:cache 会先删除 bootstrap/cache/config.php，再重新加载应用（require bootstrap/app.php），
     * 这会在请求执行过程中创建第二个 Application 实例并替换全局容器，
     * 导致请求内 request()/view() 全部无法解析（Target class [request] does not exist），
     * 并且缓存文件在报错后不会被重建，整个站点的响应会被 PHP warning 污染。
     *
     * 这里改为另起一个独立的 CLI 进程执行，当前请求的容器不会被替换。
     *
     * @return bool
     */
    public static function rebuild(): bool
    {
        return self::run(['config:cache']);
    }

    /**
     * 重建路由缓存
     *
     * 路由 URI 在注册时就把 secure_path / subscribe_path 写死了，
     * 启用路由缓存后修改这两个配置必须重建缓存才会生效。
     *
     * @return bool
     */
    public static function rebuildRoutes(): bool
    {
        return self::run(['route:cache']);
    }

    /**
     * 在独立 CLI 进程中执行 artisan 命令
     *
     * @param array $args
     * @return bool
     */
    private static function run(array $args): bool
    {
        try {
            $process = new Process(
                array_merge([self::phpBinary()], array_merge(['artisan'], $args)),
                base_path(),
                null,
                null,
                120
            );
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 获取可用的 PHP CLI 可执行文件路径
     *
     * php-fpm 环境下 PHP_BINARY 指向 php-fpm 可执行文件，不能用于执行 artisan，
     * 这里回退到同一套安装目录下的 bin/php。
     *
     * 注意：不能用 is_executable() 做探测，open_basedir 生效时它会返回 false 并抛出
     * open_basedir warning（该 warning 会污染接口响应），此处只做字符串推导，
     * 由 Process 自身去处理路径不存在的情况。
     *
     * @return string
     */
    private static function phpBinary(): string
    {
        $binary = PHP_BINARY;
        if (str_ends_with($binary, 'php-fpm')) {
            return dirname($binary, 2) . '/bin/php';
        }
        return $binary;
    }
}
