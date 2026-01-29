<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use App\Models\Risk;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\TopicPerformanceStat;
use App\Models\CurriculumPlan;
use App\Models\CurriculumTopic;
use App\Models\Document;
use App\Models\ScheduleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ControlPanelService
{
    /**
     * Панель куратора
     */
    public function getCuratorPanel(int $userId): array
    {
        $user = User::findOrFail($userId);
        $today = Carbon::today();
        $now = Carbon::now();

        // Получаем группы куратора
        $curatorGroups = $user->groups()
            ->wherePivot('role_in_group', 'curator')
            ->get();

        if ($curatorGroups->isEmpty()) {
            return [
                'groups' => [],
                'attendance' => [],
                'overdue_assignments' => [],
                'risks' => [],
                'group_averages' => [],
            ];
        }

        $groupIds = $curatorGroups->pluck('id')->toArray();

        // Получаем ID студентов из групп
        $studentIds = DB::table('group_members')
            ->whereIn('group_id', $groupIds)
            ->where('role_in_group', '!=', 'curator')
            ->pluck('user_id')
            ->toArray();

        $result = [
            'groups' => $curatorGroups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                ];
            })->toArray(),
            'attendance' => [],
            'overdue_assignments' => [],
            'risks' => [],
            'group_averages' => [],
        ];

        // Посещаемость группы (за сегодня)
        // TODO: Реализовать, когда будет модель Attendance
        $result['attendance'] = [];

        // Долги (просроченные задания)
        $overdueAssignments = Assignment::whereHas('targets', function ($q) use ($groupIds) {
            $q->whereIn('group_id', $groupIds);
        })
            ->where('due_at', '<', $now)
            ->with(['subject', 'teacher', 'targets'])
            ->get();

        $result['overdue_assignments'] = $overdueAssignments->groupBy('group_id')->map(function ($assignments, $groupId) {
            return [
                'group_id' => $groupId,
                'count' => $assignments->count(),
                'assignments' => $assignments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'title' => $a->title,
                        'subject' => $a->subject->name ?? null,
                        'due_at' => $a->due_at->format('Y-m-d H:i:s'),
                    ];
                })->toArray(),
            ];
        })->values()->toArray();

        // Red/Yellow риски
        $risks = Risk::whereIn('user_id', $studentIds)
            ->whereIn('level', ['red', 'yellow'])
            ->with(['user'])
            ->orderBy('calculated_at', 'desc')
            ->get()
            ->groupBy('level');

        $result['risks'] = [
            'red' => $risks->get('red', collect())->map(function ($risk) {
                return [
                    'id' => $risk->id,
                    'student_id' => $risk->user_id,
                    'student_fio' => $risk->user->fio ?? null,
                    'risk_type' => $risk->risk_type,
                    'score' => $risk->score,
                    'calculated_at' => $risk->calculated_at?->format('Y-m-d H:i:s'),
                ];
            })->toArray(),
            'yellow' => $risks->get('yellow', collect())->map(function ($risk) {
                return [
                    'id' => $risk->id,
                    'student_id' => $risk->user_id,
                    'student_fio' => $risk->user->fio ?? null,
                    'risk_type' => $risk->risk_type,
                    'score' => $risk->score,
                    'calculated_at' => $risk->calculated_at?->format('Y-m-d H:i:s'),
                ];
            })->toArray(),
        ];

        // Средний балл группы
        foreach ($groupIds as $groupId) {
            $groupStudentIds = DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('role_in_group', '!=', 'curator')
                ->pluck('user_id')
                ->toArray();

            $avgGrade = Grade::whereIn('student_user_id', $groupStudentIds)
                ->avg('value');

            $result['group_averages'][] = [
                'group_id' => $groupId,
                'group_name' => $curatorGroups->firstWhere('id', $groupId)->name ?? '',
                'average_grade' => $avgGrade ? round($avgGrade, 2) : null,
                'students_count' => count($groupStudentIds),
            ];
        }

        return $result;
    }

    /**
     * Панель методиста
     */
    public function getMethodistPanel(int $userId): array
    {
        $tenantId = app('tenant_id');
        $today = Carbon::today();

        $result = [
            'ktp_completion' => [],
            'failed_topics' => [],
            'check_delays' => [],
            'discipline_risks' => [],
        ];

        // КТП выполнение
        $plans = CurriculumPlan::whereHas('group', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->with(['subject', 'group', 'topics'])
            ->get();

        $result['ktp_completion'] = $plans->map(function ($plan) {
            $totalTopics = $plan->topics->count();
            
            // Для каждой темы проверяем, есть ли уроки или задания
            $completedTopics = $plan->topics->filter(function ($topic) {
                $hasLessons = DB::table('curriculum_topic_lessons')
                    ->where('topic_id', $topic->id)
                    ->exists();
                $hasAssignments = DB::table('curriculum_topic_assignments')
                    ->where('topic_id', $topic->id)
                    ->exists();
                return $hasLessons || $hasAssignments;
            })->count();

            $completionPercent = $totalTopics > 0 ? ($completedTopics / $totalTopics) * 100 : 0;

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'subject' => $plan->subject->name ?? null,
                'group' => $plan->group->name ?? null,
                'total_topics' => $totalTopics,
                'completed_topics' => $completedTopics,
                'completion_percent' => round($completionPercent, 2),
            ];
        })->toArray();

        // Проваленные темы (из topic_performance_stats)
        $failedTopics = TopicPerformanceStat::where('tenant_id', $tenantId)
            ->where('fail_percent', '>=', 30) // >= 30% неуспевающих
            ->with(['subject', 'topic'])
            ->orderBy('fail_percent', 'desc')
            ->limit(20)
            ->get();

        $result['failed_topics'] = $failedTopics->map(function ($stat) {
            return [
                'id' => $stat->id,
                'subject' => $stat->subject->name ?? null,
                'topic' => $stat->topic->title ?? null,
                'fail_percent' => $stat->fail_percent,
                'students_total' => $stat->students_total,
                'students_failed' => $stat->students_failed,
                'calculated_at' => $stat->calculated_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        // Задержки проверок (задания без оценок более 3 дней)
        $checkDelays = Assignment::where('tenant_id', $tenantId)
            ->where('due_at', '<', $today->copy()->subDays(3))
            ->whereDoesntHave('grades')
            ->with(['subject', 'teacher'])
            ->get();

        $result['check_delays'] = $checkDelays->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'subject' => $assignment->subject->name ?? null,
                'teacher' => $assignment->teacher->fio ?? null,
                'due_at' => $assignment->due_at->format('Y-m-d H:i:s'),
                'days_overdue' => $today->diffInDays($assignment->due_at),
            ];
        })->toArray();

        // Дисциплины риска (предметы с высоким процентом неуспевающих)
        $disciplineRisks = TopicPerformanceStat::where('tenant_id', $tenantId)
            ->select('subject_id', DB::raw('AVG(fail_percent) as avg_fail_percent'), DB::raw('COUNT(*) as topics_count'))
            ->groupBy('subject_id')
            ->havingRaw('AVG(fail_percent) >= 25')
            ->get();

        $subjectIds = $disciplineRisks->pluck('subject_id')->toArray();
        $subjects = \App\Models\Subject::whereIn('id', $subjectIds)->get()->keyBy('id');

        $result['discipline_risks'] = $disciplineRisks->map(function ($stat) use ($subjects) {
            return [
                'subject_id' => $stat->subject_id,
                'subject_name' => $subjects->get($stat->subject_id)->name ?? null,
                'avg_fail_percent' => round((float) $stat->avg_fail_percent, 2),
                'topics_count' => $stat->topics_count,
            ];
        })->toArray();

        return $result;
    }

    /**
     * Панель директора
     */
    public function getPrincipalPanel(int $userId): array
    {
        $tenantId = app('tenant_id');
        $today = Carbon::today();

        $result = [
            'total_risks' => [],
            'groups_with_problems' => [],
            'teacher_workload' => [],
            'documents' => [],
        ];

        // Общие риски
        $totalRisks = Risk::whereIn('user_id', function ($q) use ($tenantId) {
            $q->select('user_id')
                ->from('group_members')
                ->join('groups', 'group_members.group_id', '=', 'groups.id')
                ->where('groups.tenant_id', $tenantId);
        })
            ->whereIn('level', ['red', 'yellow'])
            ->select('level', DB::raw('COUNT(*) as count'))
            ->groupBy('level')
            ->get();

        $result['total_risks'] = [
            'red' => $totalRisks->firstWhere('level', 'red')->count ?? 0,
            'yellow' => $totalRisks->firstWhere('level', 'yellow')->count ?? 0,
            'total' => $totalRisks->sum('count'),
        ];

        // % групп с проблемами (группы с рисками >= 3 студентов)
        $groups = Group::where('tenant_id', $tenantId)->get();
        $groupsWithProblems = 0;

        foreach ($groups as $group) {
            $studentIds = $group->members()
                ->wherePivot('role_in_group', '!=', 'curator')
                ->pluck('users.id')
                ->toArray();

            $riskCount = Risk::whereIn('user_id', $studentIds)
                ->whereIn('level', ['red', 'yellow'])
                ->distinct('user_id')
                ->count('user_id');

            if ($riskCount >= 3) {
                $groupsWithProblems++;
            }
        }

        $result['groups_with_problems'] = [
            'total_groups' => $groups->count(),
            'groups_with_problems' => $groupsWithProblems,
            'percent' => $groups->count() > 0 ? round(($groupsWithProblems / $groups->count()) * 100, 2) : 0,
        ];

        // Нагрузка преподавателей (количество уроков в неделю)
        $teacherWorkload = ScheduleItem::where('tenant_id', $tenantId)
            ->where('date', '>=', $today->copy()->startOfWeek())
            ->where('date', '<=', $today->copy()->endOfWeek())
            ->whereNotNull('teacher_user_id')
            ->select('teacher_user_id', DB::raw('COUNT(*) as lessons_count'))
            ->groupBy('teacher_user_id')
            ->with('teacher')
            ->get();

        $result['teacher_workload'] = $teacherWorkload->map(function ($item) {
            return [
                'teacher_id' => $item->teacher_user_id,
                'teacher_fio' => $item->teacher->fio ?? null,
                'lessons_count' => $item->lessons_count,
            ];
        })->sortByDesc('lessons_count')->values()->toArray();

        // Документы (на согласовании, просроченные)
        $pendingDocuments = Document::whereHas('routes', function ($q) {
            $q->where('status', 'pending');
        })
            ->where('status', '!=', 'approved')
            ->count();

        $overdueDocuments = Document::where('status', 'pending')
            ->where('created_at', '<', $today->copy()->subDays(7))
            ->count();

        $result['documents'] = [
            'pending' => $pendingDocuments,
            'overdue' => $overdueDocuments,
            'total_pending' => $pendingDocuments + $overdueDocuments,
        ];

        return $result;
    }
}

