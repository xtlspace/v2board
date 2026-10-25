<?php

namespace App\Utils;

use Symfony\Component\Process\Process;

class ConfigCache
{
    /** @var array<string, bool> */
    private static array $cliBinaries = [];

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
     * 原子写入配置文件，并在配置缓存重建失败时恢复旧文件。
     */
    public static function write(string $path, string $contents): bool
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $lock = @fopen(storage_path('framework/config-cache.lock'), 'c');
        if ($lock) {
            @flock($lock, LOCK_EX);
        }

        $temporary = null;
        try {
            $oldContents = is_file($path) ? file_get_contents($path) : null;
            if ($oldContents === false) {
                $oldContents = null;
            }

            $temporary = @tempnam($directory, '.config-');
            if ($temporary === false) {
                return false;
            }
            if (@file_put_contents($temporary, $contents) === false) {
                return false;
            }
            @chmod($temporary, 0644);

            if (!@rename($temporary, $path)) {
                return false;
            }

            if (self::rebuild()) {
                return true;
            }

            self::log('配置缓存重建失败，恢复原配置文件', ['path' => $path]);
            if ($oldContents !== null) {
                $restore = @tempnam($directory, '.restore-');
                if ($restore !== false && @file_put_contents($restore, $oldContents) !== false) {
                    @chmod($restore, 0644);
                    if (!@rename($restore, $path)) {
                        self::log('原配置文件恢复失败', ['path' => $path]);
                        @unlink($restore);
                    }
                } elseif ($restore !== false) {
                    @unlink($restore);
                }
            } else {
                @unlink($path);
            }

            self::rebuild();
            return false;
        } finally {
            if ($temporary !== null && is_file($temporary)) {
                @unlink($temporary);
            }
            if ($lock) {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        }
    }

    /**
     * 在独立 CLI 进程中执行 artisan 命令
     *
     * @param array $args
     * @return bool
     */
    private static function run(array $args): bool
    {
        foreach (self::cliBinaries() as $binary) {
            try {
                $process = new Process(
                    array_merge([$binary, base_path('artisan')], $args),
                    base_path(),
                    null,
                    null,
                    120
                );
                $process->run();
                if ($process->isSuccessful()) {
                    return true;
                }
                self::log('artisan 命令执行失败', [
                    'binary' => $binary,
                    'args' => implode(' ', $args),
                    'exit' => $process->getExitCode(),
                    'stderr' => trim($process->getErrorOutput()),
                ]);
            } catch (\Throwable $e) {
                self::log('artisan 命令启动失败', [
                    'binary' => $binary,
                    'args' => implode(' ', $args),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }

    /**
     * 获取可用的 PHP CLI 可执行文件路径
     *
     * FPM 的 PHP_BINARY 可能是 php-fpm 或 php-fpm8.x，不能直接执行 artisan。
     * 优先使用 PHP_BINDIR，其次推导同安装目录下的 bin/php，最后尝试系统 PHP。
     * 每个候选都用 `php -r 'echo PHP_SAPI;'` 验证，避免把 FPM/Apache 当 CLI。
     *
     * @return array<int, string>
     */
    private static function cliBinaries(): array
    {
        $override = getenv('V2BOARD_PHP_CLI');
        if ($override) {
            $candidates = [$override];
        } else {
            $candidates = [
                defined('PHP_BINDIR') ? PHP_BINDIR . '/php' : null,
                dirname(PHP_BINARY, 2) . '/bin/php',
                '/usr/bin/php',
            ];
            if (!str_contains(basename(PHP_BINARY), 'php-fpm')) {
                $candidates[] = PHP_BINARY;
            }
        }

        $binaries = [];
        foreach (array_unique(array_filter($candidates)) as $binary) {
            if (!isset(self::$cliBinaries[$binary])) {
                self::$cliBinaries[$binary] = self::isCliBinary($binary);
            }
            if (self::$cliBinaries[$binary]) {
                $binaries[] = $binary;
            }
        }

        return $binaries;
    }

    private static function isCliBinary(string $binary): bool
    {
        try {
            $process = new Process([$binary, '-r', 'echo PHP_SAPI;'], base_path(), null, null, 5);
            $process->run();
            return $process->isSuccessful() && trim($process->getOutput()) === 'cli';
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function log(string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s%s\n",
            date('c'),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents(storage_path('logs/config-cache.log'), $line, FILE_APPEND | LOCK_EX);
    }
}
