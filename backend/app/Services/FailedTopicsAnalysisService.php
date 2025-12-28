<?php

namespace App\Services;

use App\Models\FailedTopicsAnalysis;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

class FailedTopicsAnalysisService
{
    protected float $failingGradeThreshold = 3.0; // Оценка ниже 3 считается провалом

    public function analyzeStudent(int $studentUserId, ?int $subjectId = null): array
    {
        $query = Grade::where('student_user_id', $studentUserId)
            ->where('value', '<', $this->failingGradeThreshold)
            ->with(['lesson.scheduleItem.subject', 'assignment.subject']);

        if ($subjectId) {
            $query->whereHas('lesson.scheduleItem', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            })->orWhereHas('assignment', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            });
        }

        $failedGrades = $query->get();

        $topics = [];
        foreach ($failedGrades as $grade) {
            $topic = $this->extractTopic($grade);
            if (!$topic) {
                continue;
            }

            $key = $topic['subject_id'] . '_' . $topic['topic_name'];
            if (!isset($topics[$key])) {
                $topics[$key] = [
                    'subject_id' => $topic['subject_id'],
                    'subject_name' => $topic['subject_name'],
                    'topic_name' => $topic['topic_name'],
                    'ktp_topic_id' => $topic['ktp_topic_id'],
                    'failed_attempts' => 0,
                    'grades' => [],
                    'last_attempt_date' => null,
                ];
            }

            $topics[$key]['failed_attempts']++;
            $topics[$key]['grades'][] = [
                'value' => $grade->value,
                'date' => $grade->created_at->format('Y-m-d'),
                'type' => $grade->grade_type,
            ];

            if (!$topics[$key]['last_attempt_date'] || 
                $grade->created_at->gt($topics[$key]['last_attempt_date'])) {
                $topics[$key]['last_attempt_date'] = $grade->created_at;
            }
        }

        // Сохранение в БД
        foreach ($topics as $topic) {
            $averageGrade = collect($topic['grades'])->avg('value');
            
            FailedTopicsAnalysis::updateOrCreate(
                [
                    'student_user_id' => $studentUserId,
                    'subject_id' => $topic['subject_id'],
                    'topic_name' => $topic['topic_name'],
                ],
                [
                    'ktp_topic_id' => $topic['ktp_topic_id'],
                    'failed_attempts' => $topic['failed_attempts'],
                    'average_grade' => round($averageGrade, 2),
                    'last_attempt_date' => $topic['last_attempt_date'],
                    'details_json' => [
                        'grades' => $topic['grades'],
                    ],
                ]
            );
        }

        return array_values($topics);
    }

    protected function extractTopic(Grade $grade): ?array
    {
        $subject = null;
        $topicName = null;
        $ktpTopicId = null;

        if ($grade->lesson) {
            $subject = $grade->lesson->scheduleItem?->subject;
            $topicName = $grade->lesson->topic;
            $ktpTopicId = $grade->lesson->ktp_topic_id;
        } elseif ($grade->assignment) {
            $subject = $grade->assignment->subject;
            $topicName = $grade->assignment->title; // Или можно извлечь из description
        }

        if (!$subject || !$topicName) {
            return null;
        }

        return [
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'topic_name' => $topicName,
            'ktp_topic_id' => $ktpTopicId,
        ];
    }

    public function getFailedTopics(int $studentUserId, ?int $subjectId = null): array
    {
        $query = FailedTopicsAnalysis::where('student_user_id', $studentUserId)
            ->orderBy('failed_attempts', 'desc')
            ->orderBy('last_attempt_date', 'desc');

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        return $query->with('subject')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'subject_id' => $item->subject_id,
                'subject_name' => $item->subject->name ?? null,
                'topic_name' => $item->topic_name,
                'failed_attempts' => $item->failed_attempts,
                'average_grade' => (float) $item->average_grade,
                'last_attempt_date' => $item->last_attempt_date?->format('Y-m-d'),
                'details' => $item->details_json,
            ];
        })->toArray();
    }

    public function getComplexTopics(int $subjectId, int $limit = 10): array
    {
        // Темы с наибольшим количеством провалов среди всех студентов
        return FailedTopicsAnalysis::where('subject_id', $subjectId)
            ->select('topic_name', 'ktp_topic_id', DB::raw('SUM(failed_attempts) as total_failures'), DB::raw('AVG(average_grade) as avg_grade'), DB::raw('COUNT(DISTINCT student_user_id) as affected_students'))
            ->groupBy('topic_name', 'ktp_topic_id')
            ->orderBy('total_failures', 'desc')
            ->orderBy('affected_students', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'topic_name' => $item->topic_name,
                    'ktp_topic_id' => $item->ktp_topic_id,
                    'total_failures' => (int) $item->total_failures,
                    'average_grade' => round((float) $item->avg_grade, 2),
                    'affected_students' => (int) $item->affected_students,
                ];
            })
            ->toArray();
    }
}

