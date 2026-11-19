<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RiskQueue
{
    /**
     * 标记单个用户待检查。同一用户 60 秒内只写一次。
     */
    public static function mark(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            if (!Cache::add('risk_queue_guard|' . $userId, 1, 60)) {
                return;
            }
            DB::table('v2_risk_queue')->insertOrIgnore([
                'user_id' => $userId,
                'created_at' => time(),
            ]);
        } catch (\Throwable $e) {
            // 队列写入失败不影响订阅等主流程
        }
    }

    /**
     * 批量标记用户待检查。
     */
    public static function markMany(array $userIds): void
    {
        $now = time();
        $rows = [];
        foreach (array_unique($userIds) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $rows[$id] = ['user_id' => $id, 'created_at' => $now];
            }
        }
        if (empty($rows)) {
            return;
        }
        try {
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                DB::table('v2_risk_queue')->insertOrIgnore($chunk);
            }
        } catch (\Throwable $e) {
            // 忽略，下一轮在线记录采集会再次写入
        }
    }

    /**
     * 取出待检查用户。
     *
     * @return int[]
     */
    public function take(int $limit = 500): array
    {
        $ids = DB::table('v2_risk_queue')
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('user_id');

        $result = [];
        foreach ($ids as $id) {
            $result[] = (int) $id;
        }
        return $result;
    }

    /**
     * 清理已处理用户。
     */
    public function forget(array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }
        foreach (array_chunk(array_map('intval', $userIds), 500) as $chunk) {
            DB::table('v2_risk_queue')->whereIn('user_id', $chunk)->delete();
        }
    }
}
