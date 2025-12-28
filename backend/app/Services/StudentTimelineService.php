<?php

namespace App\Services;

use App\Models\StudentTimelineEvent;
use Illuminate\Support\Facades\DB;

class StudentTimelineService
{
    /**
     * Записать событие в timeline студента
     *
     * @param string $eventType Тип события (enrollment.created, grade.created, etc.)
     * @param int $studentUserId ID студента
     * @param array $data Данные события: title, description, related_entity_type, related_entity_id, payload, event_date
     * @return StudentTimelineEvent
     */
    public function record(string $eventType, int $studentUserId, array $data = []): StudentTimelineEvent
    {
        $tenantId = app('tenant_id');
        if (!$tenantId) {
            throw new \RuntimeException('Tenant context is required');
        }

        $eventDate = $data['event_date'] ?? now();
        if (is_string($eventDate)) {
            $eventDate = \Carbon\Carbon::parse($eventDate);
        }

        return StudentTimelineEvent::create([
            'tenant_id' => $tenantId,
            'student_user_id' => $studentUserId,
            'event_type' => $eventType,
            'event_date' => $eventDate,
            'title' => $data['title'] ?? $this->getDefaultTitle($eventType),
            'description' => $data['description'] ?? null,
            'related_entity_type' => $data['related_entity_type'] ?? null,
            'related_entity_id' => $data['related_entity_id'] ?? null,
            'payload_json' => $data['payload'] ?? null,
        ]);
    }

    /**
     * Получить timeline студента с фильтрацией
     *
     * @param int $studentUserId ID студента
     * @param array $filters Фильтры: dateFrom, dateTo, event_type[]
     * @return array
     */
    public function getTimeline(int $studentUserId, array $filters = []): array
    {
        $query = StudentTimelineEvent::where('student_user_id', $studentUserId)
            ->orderBy('event_date', 'desc')
            ->orderBy('created_at', 'desc');

        if (isset($filters['dateFrom'])) {
            $query->where('event_date', '>=', $filters['dateFrom']);
        }

        if (isset($filters['dateTo'])) {
            $query->where('event_date', '<=', $filters['dateTo']);
        }

        if (isset($filters['event_type']) && is_array($filters['event_type']) && count($filters['event_type']) > 0) {
            $query->whereIn('event_type', $filters['event_type']);
        }

        return $query->get()->map(function ($event) {
            return [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'event_date' => $event->event_date->format('Y-m-d'),
                'title' => $event->title,
                'description' => $event->description,
                'related_entity_type' => $event->related_entity_type,
                'related_entity_id' => $event->related_entity_id,
                'payload' => $event->payload_json,
                'created_at' => $event->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    /**
     * Получить дефолтный заголовок для типа события
     */
    protected function getDefaultTitle(string $eventType): string
    {
        $titles = [
            'enrollment.created' => 'Зачисление',
            'attendance.marked' => 'Отметка посещаемости',
            'grade.created' => 'Оценка выставлена',
            'grade.updated' => 'Оценка изменена',
            'assignment.submitted' => 'Задание сдано',
            'assignment.late' => 'Задание сдано с опозданием',
            'risk.updated' => 'Обновление риска',
            'document.created' => 'Создан документ',
            'contest.result' => 'Результат конкурса',
            'exam.result' => 'Результат экзамена',
        ];

        return $titles[$eventType] ?? $eventType;
    }

    /**
     * TODO: Вызвать при зачислении студента
     * Пример использования:
     * $timelineService->record('enrollment.created', $studentId, [
     *     'title' => 'Зачисление',
     *     'description' => "Группа: {$group->name}",
     *     'payload' => ['group_id' => $group->id, 'group_name' => $group->name],
     * ]);
     */

    /**
     * TODO: Вызвать при отметке посещаемости
     * Пример использования в JournalController::createAttendance:
     * $timelineService->record('attendance.marked', $studentId, [
     *     'title' => $status === 'absent' ? 'Пропуск' : ($status === 'late' ? 'Опоздание' : 'Присутствие'),
     *     'description' => "Урок: {$lesson->topic}",
     *     'related_entity_type' => Attendance::class,
     *     'related_entity_id' => $attendance->id,
     *     'payload' => ['status' => $status, 'lesson_id' => $lesson->id],
     *     'event_date' => $attendance->date,
     * ]);
     */
}
