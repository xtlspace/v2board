<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class V2boardUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2board:update';

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
        $this->info('正在导入数据库请稍等...');
        foreach ($sql as $item) {
            if (!$item) continue;
            try {
                DB::statement($item);
            } catch (\Exception $e) {
            }
        }
        \Artisan::call('horizon:terminate');
        $this->info('更新完毕，队列服务已重启，你无需进行任何操作。');
    }
}
