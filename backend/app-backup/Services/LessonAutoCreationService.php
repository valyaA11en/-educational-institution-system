<?php

namespace App\Services;

use App\Models\ScheduleItem;
use App\Models\Lesson;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LessonAutoCreationService
{
    /**
     * Создать уроки из расписания на указанную дату
     */
    public function createLessonsForDate(Carbon $date): int
    {
        $scheduleItems = ScheduleItem::whereHas('version', function ($q) {
            $q->where('status', 'published');
        })
            ->where('date', $date->format('Y-m-d'))
            ->with(['subject', 'group', 'teacher', 'room', 'timeSlot'])
            ->get();

        $created = 0;

        foreach ($scheduleItems as $item) {
            $existing = Lesson::where('schedule_item_id', $item->id)
                ->where('date', $date->format('Y-m-d'))
                ->first();

            if ($existing) {
                continue;
            }

            try {
                Lesson::create([
                    'schedule_item_id' => $item->id,
                    'date' => $date,
                    'topic' => null, // TODO: можно брать из КТП если есть
                ]);

                $created++;
            } catch (\Exception $e) {
                Log::error('Failed to create lesson from schedule item', [
                    'schedule_item_id' => $item->id,
                    'date' => $date->format('Y-m-d'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * Создать уроки на период (включая даты)
     */
    public function createLessonsForPeriod(Carbon $startDate, Carbon $endDate): int
    {
        $total = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $created = $this->createLessonsForDate($current);
            $total += $created;
            $current->addDay();
        }

        return $total;
    }

    /**
     * Создать уроки на текущую неделю
     */
    public function createLessonsForCurrentWeek(): int
    {
        $monday = Carbon::now()->startOfWeek();
        $sunday = Carbon::now()->endOfWeek();

        return $this->createLessonsForPeriod($monday, $sunday);
    }
}

