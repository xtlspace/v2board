<?php

namespace App\Console\Commands;

use App\Models\ServerV2node;
use App\Services\NodeIpRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncRealmIp extends Command
{
    protected $signature = 'v2board:sync-realm-ip';
    protected $description = 'Sync realm_ip node addresses into the node IP cache';

    public function handle()
    {
        $nodes = ServerV2node::where('realm_ip', 1)->get(['id', 'host']);
        if ($nodes->isEmpty()) {
            return 0;
        }

        $registry = new NodeIpRegistry();
        foreach ($nodes as $node) {
            try {
                $registry->reportHost((int) $node->id, (string) $node->host);
            } catch (\Throwable $e) {
                Log::warning('sync realm ip failed for node ' . $node->id . ': ' . $e->getMessage());
            }
        }

        return 0;
    }
}
