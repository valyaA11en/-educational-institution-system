<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule outbox events dispatch
Schedule::job(new \App\Jobs\DispatchOutboxEvents(100))->everyMinute();

// Process webhook deliveries
Schedule::job(new \App\Jobs\DeliverWebhooks())->everyMinute();

// Check low grades notifications (last day of month)
Schedule::command('notifications:check-low-grades')->monthlyOn(1, '00:00');

// Check ticket SLA (every 10 minutes)
Schedule::command('tickets:check-sla')->everyTenMinutes();

// Recalculate risks (daily at 02:00)
Schedule::command('analytics:recalc-risks')->dailyAt('02:00');

// Exam reminders (every 6 hours)
Schedule::command('exams:remind')->everySixHours();

// Daily backups
Schedule::command('backup:database')->dailyAt('02:00');

// Auto-create lessons for tomorrow (daily at 18:00)
Schedule::command('lessons:schedule-auto-create')->dailyAt('18:00');

// Cleanup orphan files (weekly on Monday at 03:00)
Schedule::command('files:cleanup-orphans')->weeklyOn(1, '03:00');

// Generate lessons (daily at 01:00)
Schedule::command('lessons:generate --days=14')->dailyAt('01:00');

// Retry failed notification deliveries (every 5 minutes)
Schedule::command('notifications:retry-deliveries')->everyFiveMinutes();

// Cleanup old WebSocket event deliveries (daily at 03:00)
Schedule::command('ws:cleanup')->dailyAt('03:00');
