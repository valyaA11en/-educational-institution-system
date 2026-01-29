<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class JournalExportService
{
    private const CSV_HEADERS = [
        'ID',
        'Дата изменения',
        'Студент',
        'Предмет',
        'Оценка до',
        'Оценка после',
        'Причина',
        'Изменено пользователем',
    ];

    /**
     * Export grade changes as CSV rows (header + data).
     * Yields each line for streaming; tenant-scoped.
     *
     * @param  array{student_id?: int, subject_id?: int, date_from?: string, date_to?: string}  $filters
     * @return \Generator<string>
     */
    public function gradeChangesCsv(int $tenantId, array $filters): \Generator
    {
        $query = DB::table('grade_changes')
            ->join('grades', 'grade_changes.grade_id', '=', 'grades.id')
            ->leftJoin('lessons', 'grades.lesson_id', '=', 'lessons.id')
            ->leftJoin('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->leftJoin('assignments', 'grades.assignment_id', '=', 'assignments.id')
            ->leftJoin('subjects as lesson_subject', 'schedule_items.subject_id', '=', 'lesson_subject.id')
            ->leftJoin('subjects as assignment_subject', 'assignments.subject_id', '=', 'assignment_subject.id')
            ->leftJoin('users as student', 'grades.student_user_id', '=', 'student.id')
            ->leftJoin('users as changer', 'grade_changes.changed_by', '=', 'changer.id')
            ->where('grades.tenant_id', $tenantId)
            ->select([
                'grade_changes.id',
                'grade_changes.created_at',
                'grade_changes.before_json',
                'grade_changes.after_json',
                'grade_changes.reason',
                'student.fio as student_fio',
                'grades.student_user_id',
                'changer.fio as changer_fio',
                'grade_changes.changed_by',
                'lesson_subject.name as lesson_subject_name',
                'assignment_subject.name as assignment_subject_name',
            ])
            ->orderBy('grade_changes.created_at', 'desc');

        $this->applyFilters($query, $filters);

        yield $this->csvLine(self::CSV_HEADERS);

        foreach ($query->cursor() as $row) {
            $before = json_decode($row->before_json ?? '{}', true) ?? [];
            $after = json_decode($row->after_json ?? '{}', true) ?? [];
            $beforeValue = $before['value'] ?? '-';
            $afterValue = $after['value'] ?? '-';
            $studentName = $row->student_fio ?? "ID: {$row->student_user_id}";
            $subjectName = $row->lesson_subject_name ?? $row->assignment_subject_name ?? '-';
            $changerName = $row->changer_fio ?? "ID: {$row->changed_by}";
            $createdAt = $row->created_at ? date('d.m.Y H:i', strtotime($row->created_at)) : '-';

            yield $this->csvLine([
                $row->id,
                $createdAt,
                $studentName,
                $subjectName,
                $beforeValue,
                $afterValue,
                $row->reason ?? '-',
                $changerName,
            ]);
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array{student_id?: int, subject_id?: int, date_from?: string, date_to?: string}  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['student_id'])) {
            $query->where('grades.student_user_id', (int) $filters['student_id']);
        }
        if (!empty($filters['subject_id'])) {
            $query->where(function ($q) use ($filters): void {
                $q->where('schedule_items.subject_id', (int) $filters['subject_id'])
                    ->orWhere('assignments.subject_id', (int) $filters['subject_id']);
            });
        }
        if (!empty($filters['date_from'])) {
            $query->where('grade_changes.created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('grade_changes.created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
    }

    /**
     * @param  array<int|string>  $fields
     */
    private function csvLine(array $fields): string
    {
        $escaped = array_map(fn ($f) => '"' . str_replace('"', '""', (string) $f) . '"', $fields);

        return implode(';', $escaped) . "\n";
    }
}
