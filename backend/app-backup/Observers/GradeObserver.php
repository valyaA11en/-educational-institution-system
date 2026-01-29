<?php

namespace App\Observers;

use App\Models\Grade;
use App\Services\StudentTimelineService;
use App\Services\StudentPortfolioService;

class GradeObserver
{
    public function __construct(
        private StudentTimelineService $timelineService,
        private StudentPortfolioService $portfolioService
    ) {}

    public function created(Grade $grade): void
    {
        if ($grade->student_user_id) {
            $subject = $grade->lesson?->scheduleItem?->subject ?? $grade->assignment?->subject;
            
            // Записываем в timeline
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

            // Добавляем в портфолио, если оценка >= 4
            if ($grade->assignment_id) {
                $minGrade = config('portfolio.min_grade_for_auto_add', 4.0);
                $this->portfolioService->addFromAssignment($grade, $minGrade);
            }
        }
    }

    public function updated(Grade $grade): void
    {
        if ($grade->student_user_id && $grade->wasChanged('value')) {
            $subject = $grade->lesson?->scheduleItem?->subject ?? $grade->assignment?->subject;
            
            // Записываем в timeline
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

            // Если оценка изменилась и стала >= 4, добавляем в портфолио
            if ($grade->assignment_id && $grade->value >= 4.0) {
                $minGrade = config('portfolio.min_grade_for_auto_add', 4.0);
                $this->portfolioService->addFromAssignment($grade, $minGrade);
            }
        }
    }
}
