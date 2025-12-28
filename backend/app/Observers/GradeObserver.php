<?php

namespace App\Observers;

use App\Models\Grade;
use App\Services\StudentTimelineService;

class GradeObserver
{
    public function __construct(
        private StudentTimelineService $timelineService
    ) {}

    public function created(Grade $grade): void
    {
        if ($grade->student_user_id) {
            $subject = $grade->lesson?->scheduleItem?->subject ?? $grade->assignment?->subject;
            
            $this->timelineService->record('grade.created', $grade->student_user_id, [
                'title' => "Оценка: {$grade->value}",
                'description' => $subject ? "По предмету: {$subject->name}" : null,
                'related_entity_type' => Grade::class,
                'related_entity_id' => $grade->id,
                'payload' => [
                    'value' => $grade->value,
                    'grade_type' => $grade->grade_type,
                    'subject_id' => $subject?->id,
                    'subject_name' => $subject?->name,
                    'lesson_id' => $grade->lesson_id,
                    'assignment_id' => $grade->assignment_id,
                ],
                'event_date' => $grade->created_at,
            ]);
        }
    }

    public function updated(Grade $grade): void
    {
        if ($grade->student_user_id && $grade->wasChanged('value')) {
            $subject = $grade->lesson?->scheduleItem?->subject ?? $grade->assignment?->subject;
            
            $this->timelineService->record('grade.updated', $grade->student_user_id, [
                'title' => "Оценка изменена: {$grade->getOriginal('value')} → {$grade->value}",
                'description' => $subject ? "По предмету: {$subject->name}" : null,
                'related_entity_type' => Grade::class,
                'related_entity_id' => $grade->id,
                'payload' => [
                    'old_value' => $grade->getOriginal('value'),
                    'new_value' => $grade->value,
                    'grade_type' => $grade->grade_type,
                    'subject_id' => $subject?->id,
                    'subject_name' => $subject?->name,
                ],
                'event_date' => $grade->updated_at,
            ]);
        }
    }
}

