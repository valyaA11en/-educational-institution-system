<?php

namespace App\Console\Commands;

use App\Services\LessonAutoCreationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AutoCreateLessons extends Command
{
    protected $signature = 'lessons:auto-create
                            {--date= : Дата для создания уроков (Y-m-d)}
                            {--week : Создать уроки на текущую неделю}
                            {--period= : Период в формате start_date,end_date (Y-m-d,Y-m-d)}';

    protected $description = 'Автоматическое создание уроков из расписания';

    public function handle(LessonAutoCreationService $service): int
    {
        if ($this->option('week')) {
            $count = $service->createLessonsForCurrentWeek();
            $this->info("Создано уроков: {$count}");
            return 0;
        }

        if ($this->option('period')) {
            [$start, $end] = explode(',', $this->option('period'));
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            $count = $service->createLessonsForPeriod($startDate, $endDate);
            $this->info("Создано уроков за период: {$count}");
            return 0;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $count = $service->createLessonsForDate($date);
        $this->info("Создано уроков на {$date->format('Y-m-d')}: {$count}");

        return 0;
    }
}


