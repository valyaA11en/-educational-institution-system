<?php

namespace App\Observers;

use App\Models\ScheduleVersion;
use App\Services\LessonGeneratorService;
use Carbon\Carbon;

class ScheduleVersionObserver
{
    public function __construct(
        private LessonGeneratorService $lessonGenerator
    ) {}

    public function updated(ScheduleVersion $version): void
    {
        // При публикации версии генерировать уроки
        if ($version->wasChanged('status') && $version->status === 'published') {
            $tenantId = $version->tenant_id;
            
            // Генерировать на ближайшие 30 дней
            $from = Carbon::today();
            $to = Carbon::today()->addDays(30);

            try {
                $this->lessonGenerator->generateForDateRange(
                    $tenantId,
                    $from,
                    $to,
                    $version->id
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to generate lessons after schedule version publish', [
                    'version_id' => $version->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}


