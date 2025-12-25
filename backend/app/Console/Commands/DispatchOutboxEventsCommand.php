<?php

namespace App\Console\Commands;

use App\Jobs\DispatchOutboxEvents;
use Illuminate\Console\Command;

class DispatchOutboxEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:dispatch {--batch-size=100 : Number of events to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually dispatch pending outbox events';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');

        $this->info("Dispatching outbox events (batch size: {$batchSize})...");

        DispatchOutboxEvents::dispatch($batchSize);

        $this->info('Outbox events dispatched successfully.');

        return self::SUCCESS;
    }
}

