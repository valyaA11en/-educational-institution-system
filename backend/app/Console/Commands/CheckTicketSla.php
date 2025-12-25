<?php

namespace App\Console\Commands;

use App\Services\Ticket\SlaService;
use Illuminate\Console\Command;

class CheckTicketSla extends Command
{
    protected $signature = 'tickets:check-sla';

    protected $description = 'Check ticket SLA and send reminders';

    public function handle(SlaService $slaService): int
    {
        $this->info('Checking overdue tickets...');
        $slaService->checkOverdue();

        $this->info('Sending SLA reminders...');
        $slaService->sendReminders();

        $this->info('SLA check completed');

        return Command::SUCCESS;
    }
}

