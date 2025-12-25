<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule outbox events dispatch
Schedule::job(new \App\Jobs\DispatchOutboxEvents(100))->everyMinute();

// Check low grades notifications (last day of month)
Schedule::command('notifications:check-low-grades')->monthlyOn(1, '00:00');
