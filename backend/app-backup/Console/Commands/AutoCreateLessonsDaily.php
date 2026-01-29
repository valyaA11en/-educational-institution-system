<?php

namespace App\Console\Commands;

use App\Services\LessonAutoCreationService;
use Illuminate\Console\Command;

class AutoCreateLessonsDaily extends Command
{
    protected $signature = 'lessons:auto-create-daily';

    protected $description = 'Автоматическое создание уроков на завтра';

    public function handle(LessonAutoCreationService $service): int
    {
        $count = $service->createLessonsForDate(now()->addDay());
        $this->info("Создано уроков на завтра: {$count}");
        return 0;
    }
}


