<?php

namespace App\Services;

use App\Models\StudentPortfolioItem;
use App\Models\Grade;
use App\Models\ContestResult;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class StudentPortfolioService
{
    /**
     * Автоматически добавить элемент портфолио из результата конкурса (призовое место)
     */
    public function addFromContestResult(ContestResult $result, int $minPlace = 3): ?StudentPortfolioItem
    {
        // Добавляем только призовые места (1-3 по умолчанию)
        if ($result->place > $minPlace || !$result->place) {
            return null;
        }

        $submission = $result->submission;
        if (!$submission || !$submission->participant_user_id) {
            return null;
        }

        $contest = $result->contest;
        $placeLabels = [1 => '1 место', 2 => '2 место', 3 => '3 место'];
        $placeLabel = $placeLabels[$result->place] ?? "{$result->place} место";

        // Проверяем, не добавлен ли уже этот результат
        $existing = StudentPortfolioItem::where('student_user_id', $studentId)
            ->where('related_entity_type', ContestResult::class)
            ->where('related_entity_id', $result->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return StudentPortfolioItem::create([
            'tenant_id' => app('tenant_id'),
            'student_user_id' => $studentId,
            'type' => 'contest',
            'title' => "{$placeLabel} в конкурсе \"{$contest->title}\"",
            'description' => "Итоговый балл: {$result->final_score}",
            'related_entity_type' => ContestResult::class,
            'related_entity_id' => $result->id,
            'file_id' => null,
            'is_featured' => $result->place === 1, // Первое место - featured
        ]);
    }

    /**
     * Автоматически добавить элемент портфолио из сертификата (документа)
     */
    public function addFromCertificate(Document $document): ?StudentPortfolioItem
    {
        // Проверяем, что документ является сертификатом
        if ($document->type !== 'certificate' && $document->type !== 'сертификат') {
            return null;
        }

        // Получаем student_id из data_json
        $data = $document->data_json ?? [];
        $studentId = $data['student_id'] ?? null;

        if (!$studentId) {
            return null;
        }

        // Проверяем, не добавлен ли уже этот документ
        $existing = StudentPortfolioItem::where('student_user_id', $studentId)
            ->where('related_entity_type', Document::class)
            ->where('related_entity_id', $document->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return StudentPortfolioItem::create([
            'tenant_id' => app('tenant_id'),
            'student_user_id' => $studentId,
            'type' => 'certificate',
            'title' => "Сертификат: {$document->number}",
            'description' => $document->data_json['description'] ?? null,
            'related_entity_type' => Document::class,
            'related_entity_id' => $document->id,
            'file_id' => null, // TODO: Если у документа есть файл, добавить file_id
            'is_featured' => false,
        ]);
    }

    /**
     * Автоматически добавить элемент портфолио из задания с высокой оценкой
     */
    public function addFromAssignment(Grade $grade, float $minGrade = 4.0): ?StudentPortfolioItem
    {
        if (!$grade->assignment_id || !$grade->student_user_id) {
            return null;
        }

        if ($grade->value < $minGrade) {
            return null;
        }

        $assignment = $grade->assignment;
        if (!$assignment) {
            return null;
        }

        // Проверяем, не добавлен ли уже этот элемент
        $existing = StudentPortfolioItem::where('student_user_id', $grade->student_user_id)
            ->where('related_entity_type', Grade::class)
            ->where('related_entity_id', $grade->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $subject = $assignment->subject;
        $subjectName = $subject ? $subject->name : '';

        return StudentPortfolioItem::create([
            'tenant_id' => app('tenant_id'),
            'student_user_id' => $grade->student_user_id,
            'type' => 'assignment',
            'title' => "Задание: {$assignment->title}",
            'description' => "Оценка: {$grade->value} • {$subjectName}",
            'related_entity_type' => Grade::class,
            'related_entity_id' => $grade->id,
            'file_id' => null,
            'is_featured' => $grade->value >= 5.0, // Отлично - featured
        ]);
    }

    /**
     * Получить портфолио студента
     */
    public function getPortfolio(int $studentId, array $filters = []): array
    {
        $query = StudentPortfolioItem::where('student_user_id', $studentId)
            ->orderBy('is_featured', 'desc')
            ->orderBy('created_at', 'desc');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        return $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'description' => $item->description,
                'related_entity_type' => $item->related_entity_type,
                'related_entity_id' => $item->related_entity_id,
                'file_id' => $item->file_id,
                'is_featured' => $item->is_featured,
                'created_at' => $item->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }
}

