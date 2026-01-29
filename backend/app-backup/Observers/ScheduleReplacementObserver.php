<?php

namespace App\Observers;

use App\Models\ScheduleReplacement;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleReplacementObserver
{
    public function created(ScheduleReplacement $replacement): void
    {
        $this->updateLessonFromReplacement($replacement);
    }

    public function updated(ScheduleReplacement $replacement): void
    {
        $this->updateLessonFromReplacement($replacement);
    }

    protected function updateLessonFromReplacement(ScheduleReplacement $replacement): void
    {
        $scheduleItem = $replacement->scheduleItem;
        if (!$scheduleItem) {
            return;
        }

        $lesson = Lesson::where('schedule_item_id', $scheduleItem->id)
            ->where('date', $replacement->date)
            ->first();

        if (!$lesson) {
            return;
        }

        try {
            DB::beginTransaction();

            // TODO: Обновить teacher/room если они изменились в replacement
            // ScheduleReplacement имеет new_teacher_user_id и new_room_id
            // Но lesson не хранит teacher/room напрямую, они в schedule_item
            // Поэтому обновление lesson при replacement не требуется - изменения в schedule_item

            // Outbox событие
            \App\Models\OutboxEvent::create([
                'event_type' => 'lesson.updated',
                'entity_type' => Lesson::class,
                'entity_id' => $lesson->id,
                'payload_json' => [
                    'lesson_id' => $lesson->id,
                    'schedule_item_id' => $scheduleItem->id,
                    'replacement_id' => $replacement->id,
                    'date' => $replacement->date->format('Y-m-d'),
                ],
                'idempotency_key' => 'lesson_updated_' . $lesson->id . '_' . now()->timestamp,
                'status' => 'new',
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update lesson from replacement', [
                'replacement_id' => $replacement->id,
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

