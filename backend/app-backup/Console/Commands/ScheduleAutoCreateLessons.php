<?php

namespace App\Console\Commands;

use App\Services\LessonAutoCreationService;
use Illuminate\Console\Command;

class ScheduleAutoCreateLessons extends Command
{
    protected $signature = 'lessons:schedule-auto-create';

    protected $description = 'Автоматическое создание уроков на завтра (для cron)';

    public function handle(LessonAutoCreationService $service): int
    {
        $count = $service->createLessonsForDate(now()->addDay());
        $this->info("Создано уроков на завтра: {$count}");
        return 0;
    }
}


