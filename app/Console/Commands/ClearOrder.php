<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ClearOrder extends Command
{
    protected $signature = 'clear:order';
    protected $description = '清理已取消/已完成/已折抵的历史订单';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // 1. 清理超过7天的已取消订单（status=2）
        $cancelled = Order::where('status', 2)
            ->where('created_at', '<', time() - 7 * 86400);
        $count = $cancelled->count();
        if ($cancelled->delete()) {
            $this->info("已删除{$count}个已取消的订单");
        }
		
        // 2. 清理月付超40天，季付超100天，半年付超190天，年付超380天的已完成订单（status=3）
        $now = time();
        $completedRules = [
            'month_price'      => 40,
            'quarter_price'    => 100,
            'half_year_price'  => 190,
            'year_price'       => 380,
        ];

        foreach ($completedRules as $period => $days) {
            $count = Order::where('status', 3)
                ->where('period', $period)
                ->where('updated_at', '<', $now - $days * 86400)
                ->delete();
            if ($count) {
                $this->info("已删除{$count}个已完成{$period}订单");
            }
        }

        // 3. 清理超过10天的折抵订单（status=4）
        $count = Order::where('status', 4)
            ->where('updated_at', '<', $now - 10 * 86400)
            ->delete();
        if ($count) {
            $this->info("已删除{$count}个已折抵订单");
        }
		
    }
}