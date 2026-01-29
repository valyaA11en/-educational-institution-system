<?php

namespace App\Services;

use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Models\Lesson;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LessonGeneratorService
{
    /**
     * Генерировать уроки для диапазона дат
     */
    public function generateForDateRange(
        int $tenantId,
        Carbon $from,
        Carbon $to,
        ?int $versionId = null
    ): int {
        $query = ScheduleItem::where('tenant_id', $tenantId)
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);

        if ($versionId) {
            $query->where('version_id', $versionId);
        } else {
            $query->whereHas('version', function ($q) {
                $q->where('status', 'published');
            });
        }

        $scheduleItems = $query->with(['version', 'subject', 'group'])->get();

        $created = 0;
        $current = $from->copy();

        while ($current->lte($to)) {
            $dateStr = $current->format('Y-m-d');
            $itemsForDate = $scheduleItems->filter(function ($item) use ($dateStr) {
                return $item->date->format('Y-m-d') === $dateStr;
            });

            foreach ($itemsForDate as $item) {
                $existing = Lesson::where('tenant_id', $tenantId)
                    ->where('schedule_item_id', $item->id)
                    ->where('date', $dateStr)
                    ->first();

                if ($existing) {
                    continue;
                }

                try {
                    DB::beginTransaction();
                    
                    $lesson = Lesson::create([
                        'tenant_id' => $tenantId,
                        'schedule_item_id' => $item->id,
                        'date' => $current,
                        'status' => 'planned',
                        'topic' => null,
                        'ktp_topic_id' => null,
                        'created_by' => null, // TODO: установить текущего пользователя если есть
                    ]);

                    // Outbox событие
                    \App\Models\OutboxEvent::create([
                        'event_type' => 'lesson.created',
                        'entity_type' => Lesson::class,
                        'entity_id' => $lesson->id,
                        'payload_json' => [
                            'lesson_id' => $lesson->id,
                            'schedule_item_id' => $item->id,
                            'date' => $dateStr,
                            'group_id' => $item->group_id,
                            'subject_id' => $item->subject_id,
                        ],
                        'idempotency_key' => 'lesson_created_' . $lesson->id . '_' . now()->timestamp,
                        'status' => 'new',
                    ]);

                    DB::commit();
                    $created++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to create lesson from schedule item', [
                        'schedule_item_id' => $item->id,
                        'date' => $dateStr,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $current->addDay();
        }

        return $created;
    }

    /**
     * Генерировать уроки для термина (семестра)
     */
    public function generateForTerm(int $tenantId, int $termId, ?int $versionId = null): int
    {
        // TODO: Получить даты термина из модели Term
        // Пока используем ближайшие 30 дней
        $from = Carbon::today();
        $to = Carbon::today()->addDays(30);

        return $this->generateForDateRange($tenantId, $from, $to, $versionId);
    }
}

