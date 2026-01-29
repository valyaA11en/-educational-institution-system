<?php

namespace App\Console\Commands;

use App\Services\WsEventDeliveryService;
use Illuminate\Console\Command;

class CleanupWsEventDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ws:cleanup {--days=7 : Number of days to keep acked deliveries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old acknowledged WebSocket event deliveries';

    /**
     * Execute the console command.
     */
    public function handle(WsEventDeliveryService $service): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up WebSocket event deliveries older than {$days} days...");

        $deleted = $service->cleanupOldDeliveries($days);

        $this->info("Deleted {$deleted} old deliveries.");

        return Command::SUCCESS;
    }
}


