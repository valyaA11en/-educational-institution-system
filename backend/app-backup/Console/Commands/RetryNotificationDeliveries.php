<?php

namespace App\Console\Commands;

use App\Services\WebSocketDeliveryService;
use Illuminate\Console\Command;

class RetryNotificationDeliveries extends Command
{
    protected $signature = 'notifications:retry-deliveries';

    protected $description = 'Повторная отправка неотправленных уведомлений';

    public function handle(WebSocketDeliveryService $service): int
    {
        $this->info('Retrying failed notification deliveries...');
        
        $sent = $service->retryFailedDeliveries();
        
        $this->info("Retried {$sent} notification deliveries");

        return 0;
    }
}


