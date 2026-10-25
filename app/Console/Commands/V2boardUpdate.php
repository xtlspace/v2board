<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class V2boardUpdate extends Command
{
    private const IGNORED_ERROR_CODES = [
        1017, // 源表不存在（RENAME 已执行）
        1050, // 表已存在
        1054, // 旧库/新库列差异（CHANGE 不存在的列）
        1060, // 列已存在
        1061, // 索引已存在
        1062, // 唯一键重复（重复数据语句）
        1072, // 索引引用了旧版已移除的列
        1091, // 待删除的列/索引不存在
        1146, // 旧节点表不存在
        1826, // 外键重复
        1828, // 外键不存在
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2board:update {--strict}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'v2board 更新';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Artisan::call('config:cache');
        DB::connection()->getPdo();
        $file = base_path() . '/database/update.sql';
        if (!is_file($file)) {
            abort(500, '数据库文件不存在');
        }
        $sql = \App\Utils\SqlImporter::statements($file);
        if (!is_array($sql) || empty($sql)) {
            abort(500, '数据库文件格式有误');
        }

        $logFile = storage_path('logs/v2board-update-sql.log');
        @file_put_contents(
            $logFile,
            sprintf("[%s] 开始升级，共 %d 条语句\n", date('c'), count($sql))
        );

        $this->info('正在导入数据库请稍等...');
        $expectedErrors = 0;
        $unexpectedErrors = 0;
        $errorCount = 0;

        foreach ($sql as $index => $item) {
            if (!$item) continue;
            try {
                DB::statement($item);
            } catch (\Throwable $e) {
                $errorCount++;
                $ignored = $this->isIgnoredError($e);
                if ($ignored) {
                    $expectedErrors++;
                } else {
                    $unexpectedErrors++;
                    $this->error(sprintf('#%d SQL 执行失败: %s', $index + 1, $this->shortError($e->getMessage())));
                }

                $code = $e instanceof QueryException ? ($e->errorInfo[1] ?? '-') : '-';
                @file_put_contents(
                    $logFile,
                    sprintf(
                        "[%s] #%d %s code=%s\nSQL: %s\nERROR: %s\n---\n",
                        date('c'),
                        $index + 1,
                        $ignored ? 'IGNORED' : 'FAILED',
                        $code,
                        $item,
                        $e->getMessage()
                    ),
                    FILE_APPEND | LOCK_EX
                );
            }
        }

        \Artisan::call('horizon:terminate');

        if ($errorCount > 0) {
            $this->warn(sprintf(
                'SQL 共失败 %d 条，其中可忽略 %d 条、未预期 %d 条；日志: %s',
                $errorCount,
                $expectedErrors,
                $unexpectedErrors,
                $logFile
            ));
        }

        if ($unexpectedErrors > 0 && $this->option('strict')) {
            $this->error('数据库升级存在未预期错误');
            return self::FAILURE;
        }

        $this->info('更新完毕，队列服务已重启，你无需进行任何操作。');
        return self::SUCCESS;
    }

    private function isIgnoredError(\Throwable $e): bool
    {
        if (!$e instanceof QueryException) {
            return false;
        }
        $code = (int) ($e->errorInfo[1] ?? 0);
        return in_array($code, self::IGNORED_ERROR_CODES, true);
    }

    private function shortError(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message));
        return strlen($message) > 300 ? substr($message, 0, 300) . '...' : $message;
    }
}
