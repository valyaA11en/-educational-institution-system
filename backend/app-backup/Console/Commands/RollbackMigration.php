<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RollbackMigration extends Command
{
    protected $signature = 'migrate:rollback-safe {steps=1}';
    protected $description = 'Rollback migrations with safety checks';

    public function handle(): int
    {
        $steps = (int) $this->argument('steps');

        $this->info("Rolling back {$steps} migration(s)...");

        // Create backup before rollback
        $this->call('backup:database');

        Artisan::call('migrate:rollback', ['--step' => $steps]);

        $this->info('Rollback completed');

        return 0;
    }
}


