<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RiskQueue;
use App\Services\RiskService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckRisk extends Command
{
    protected $signature = 'risk:notify';

    protected $description = '检查新增风险并通过 Telegram 通知管理员';

    public function handle()
    {
        if (!config('v2board.telegram_bot_enable', 0)) {
            return self::SUCCESS;
        }

        $now = time();

        $service = app(RiskService::class);
        $rules = array_values(array_filter(
            $service->normalizeRules(config('v2board.risk_rules', [])),
            function ($rule) {
                return !empty($rule['enabled']) && (!isset($rule['notify']) || !empty($rule['notify']));
            }
        ));

        if (empty($rules)) {
            return self::SUCCESS;
        }

        $queue = app(RiskQueue::class);
        $userIds = $queue->take(500);
        if (empty($userIds)) {
            return self::SUCCESS;
        }

        $ruleIds = array_map(function ($rule) {
            return (string) $rule['id'];
        }, $rules);

        try {
            $rows = $service->buildRows($rules, $ruleIds, 0, $userIds);
        } catch (\Throwable $e) {
            Log::warning('risk:notify build failed: ' . $e->getMessage());
            return self::SUCCESS;
        }

        $ruleNames = [];
        foreach ($rules as $rule) {
            $ruleNames[(string) $rule['id']] = (string) $rule['name'];
        }

        $stateKey = 'risk_notify_state';
        $state = Cache::get($stateKey, []);
        if (!is_array($state)) {
            $state = [];
        }

        $cooldown = 24 * 3600;
        $maxItems = 20;

        $items = [];
        $newState = $state;

        foreach ($rows as $row) {
            if (!empty($row['ignored'])) {
                continue;
            }
            $userId = (int) $row['user_id'];
            foreach ((array) $row['risk_tags'] as $ruleId) {
                $key = $userId . ':' . $ruleId;
                $lastAt = (int) ($state[$key] ?? 0);
                if ($lastAt > 0 && $now - $lastAt < $cooldown) {
                    continue;
                }
                $items[] = [
                    'user_id' => $userId,
                    'rule' => $ruleNames[$ruleId] ?? $ruleId,
                    'sub' => $this->formatRegions($row['sub_regions'] ?? []),
                    'alive' => $this->formatRegions($row['alive_provinces'] ?? []),
                ];
                $newState[$key] = $now;
            }
        }

        $expire = $now - 30 * 86400;
        foreach ($newState as $key => $ts) {
            if ((int) $ts < $expire) {
                unset($newState[$key]);
            }
        }

        if (empty($items)) {
            $queue->forget($userIds);
            Cache::put($stateKey, $newState, 30 * 86400);
            return self::SUCCESS;
        }

        $recipients = $this->recipients();
        if (empty($recipients)) {
            $this->warn('risk:notify 没有可用的 Telegram 接收人，保留待检查队列。');
            return self::SUCCESS;
        }

        $message = $this->buildMessage($items, $maxItems, $now);
        $sent = $this->send($recipients, $message);

        if ($sent) {
            $queue->forget($userIds);
            Cache::put($stateKey, $newState, 30 * 86400);
        }

        return self::SUCCESS;
    }

    private function formatRegions(array $regions): string
    {
        $regions = array_values(array_filter(array_map('strval', $regions), function ($item) {
            return $item !== '';
        }));
        if (empty($regions)) {
            return '-';
        }
        if (count($regions) > 6) {
            return implode('、', array_slice($regions, 0, 6)) . ' 等' . count($regions) . '个';
        }
        return implode('、', $regions);
    }

    private function buildMessage(array $items, int $maxItems, int $now): string
    {
        $total = count($items);
        $lines = [];
        $lines[] = '【风险提醒】新增 ' . $total . ' 条风险';
        $lines[] = date('Y-m-d H:i', $now);
        $lines[] = '';

        $shown = 0;
        foreach ($items as $index => $item) {
            if ($shown >= $maxItems) {
                break;
            }
            $block = [
                ($index + 1) . '. 用户 ' . $item['user_id'] . ' · ' . $item['rule'],
                '   订阅：' . $item['sub'] . ' ｜ 在线：' . $item['alive'],
            ];
            if (mb_strlen(implode("\n", array_merge($lines, $block))) > 3800) {
                break;
            }
            $lines = array_merge($lines, $block);
            $shown++;
        }

        if ($total > $shown) {
            $lines[] = '';
            $lines[] = '另有 ' . ($total - $shown) . ' 条未显示，请到后台「风险筛查」查看。';
        }

        return implode("\n", $lines);
    }

    private function recipients(): array
    {
        $ids = [];
        $admins = User::where('is_admin', 1)
            ->whereNotNull('telegram_id')
            ->where('telegram_id', '!=', '')
            ->pluck('telegram_id');
        foreach ($admins as $id) {
            $id = (int) $id;
            if ($id !== 0) {
                $ids[$id] = true;
            }
        }
        return array_keys($ids);
    }

    private function send(array $chatIds, string $message): bool
    {
        $telegram = new TelegramService();
        $sent = false;
        foreach ($chatIds as $chatId) {
            try {
                $telegram->sendMessage((int) $chatId, $message);
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('risk:notify send failed for ' . $chatId . ': ' . $e->getMessage());
            }
        }
        return $sent;
    }
}
