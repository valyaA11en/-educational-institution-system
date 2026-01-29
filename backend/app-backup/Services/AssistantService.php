<?php

namespace App\Services;

use App\Models\User;
use App\Models\Assignment;
use App\Models\AssignmentTarget;
use App\Models\ScheduleItem;
use App\Models\Lesson;
use App\Models\Grade;
use App\Models\Risk;
use App\Models\Material;
use App\Models\MaterialTarget;
use App\Models\Document;
use App\Models\DocumentRoute;
use App\Models\Ticket;
use App\Models\Group;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssistantService
{
    /**
     * Получить задачи на сегодня для студента
     */
    public function getTodayForStudent(int $userId): array
    {
        $user = User::findOrFail($userId);
        $today = Carbon::today();
        $now = Carbon::now();

        $result = [
            'lessons_today' => [],
            'deadlines_24h' => [],
            'deadlines_3d' => [],
            'overdue_assignments' => [],
            'risks' => [],
            'unread_materials' => [],
        ];

        // Пары сегодня
        $userGroupIds = $user->groups()->pluck('groups.id')->toArray();
        
        $scheduleItems = ScheduleItem::whereIn('group_id', $userGroupIds)
            ->where('date', $today)
            ->whereHas('version', function ($q) {
                $q->where('status', 'published');
            })
            ->with(['subject', 'room', 'timeSlot', 'teacher', 'group'])
            ->orderBy('time_slot_id')
            ->get();

        foreach ($scheduleItems as $item) {
            $result['lessons_today'][] = [
                'id' => $item->id,
                'subject' => $item->subject->name ?? null,
                'room' => $item->room->name ?? null,
                'time' => $item->timeSlot ? ($item->timeSlot->start_time . ' - ' . $item->timeSlot->end_time) : null,
                'teacher' => $item->teacher->fio ?? null,
            ];
        }

        // Дедлайны (24 часа)
        $deadlines24h = Assignment::whereHas('targets', function ($q) use ($user) {
            $q->where('target_user_id', $user->id)
                ->orWhere(function ($q2) use ($user) {
                    $q2->whereHas('group.members', function ($q3) use ($user) {
                        $q3->where('users.id', $user->id);
                    });
                });
        })
            ->where('due_at', '>=', $now)
            ->where('due_at', '<=', $now->copy()->addHours(24))
            ->with(['subject', 'teacher'])
            ->get();

        foreach ($deadlines24h as $assignment) {
            $result['deadlines_24h'][] = [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'subject' => $assignment->subject->name ?? null,
                'due_at' => $assignment->due_at->format('Y-m-d H:i:s'),
                'hours_left' => $now->diffInHours($assignment->due_at, false),
            ];
        }

        // Дедлайны (3 дня)
        $deadlines3d = Assignment::whereHas('targets', function ($q) use ($user) {
            $q->where('target_user_id', $user->id)
                ->orWhere(function ($q2) use ($user) {
                    $q2->whereHas('group.members', function ($q3) use ($user) {
                        $q3->where('users.id', $user->id);
                    });
                });
        })
            ->where('due_at', '>', $now->copy()->addHours(24))
            ->where('due_at', '<=', $now->copy()->addDays(3))
            ->with(['subject', 'teacher'])
            ->get();

        foreach ($deadlines3d as $assignment) {
            $result['deadlines_3d'][] = [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'subject' => $assignment->subject->name ?? null,
                'due_at' => $assignment->due_at->format('Y-m-d H:i:s'),
                'days_left' => $now->diffInDays($assignment->due_at, false),
            ];
        }

        // Долги (просроченные задания)
        $overdue = Assignment::whereHas('targets', function ($q) use ($user) {
            $q->where('target_user_id', $user->id)
                ->orWhere(function ($q2) use ($user) {
                    $q2->whereHas('group.members', function ($q3) use ($user) {
                        $q3->where('users.id', $user->id);
                    });
                });
        })
            ->where('due_at', '<', $now)
            ->whereDoesntHave('grades', function ($q) use ($user) {
                $q->where('student_user_id', $user->id)
                    ->where('assignment_id', DB::raw('assignments.id'));
            })
            ->with(['subject', 'teacher'])
            ->get();

        foreach ($overdue as $assignment) {
            $result['overdue_assignments'][] = [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'subject' => $assignment->subject->name ?? null,
                'due_at' => $assignment->due_at->format('Y-m-d H:i:s'),
                'days_overdue' => $now->diffInDays($assignment->due_at, false),
            ];
        }

        // Риски
        $risks = Risk::where('user_id', $userId)
            ->whereIn('level', ['red', 'yellow'])
            ->orderBy('calculated_at', 'desc')
            ->get();

        foreach ($risks as $risk) {
            $result['risks'][] = [
                'id' => $risk->id,
                'level' => $risk->level,
                'risk_type' => $risk->risk_type,
                'score' => $risk->score,
                'calculated_at' => $risk->calculated_at?->format('Y-m-d H:i:s'),
            ];
        }

        // Непрочитанные материалы
        // Предполагаем, что материалы считаются прочитанными, если есть запись в MaterialTarget
        // или если материал был создан более 7 дней назад (упрощенная логика)
        $unreadMaterials = Material::whereHas('targets', function ($q) use ($user) {
            $q->where('student_user_id', $user->id)
                ->orWhereHas('group.members', function ($q2) use ($user) {
                    $q2->where('users.id', $user->id);
                });
        })
            ->where('created_at', '>=', $today->copy()->subDays(7))
            ->with(['subject', 'creator'])
            ->get();

        foreach ($unreadMaterials as $material) {
            // TODO: Добавить проверку на фактическое прочтение через отдельную таблицу acknowledgments
            $result['unread_materials'][] = [
                'id' => $material->id,
                'title' => $material->title,
                'subject' => $material->subject->name ?? null,
                'created_at' => $material->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $result;
    }

    /**
     * Получить задачи на сегодня для преподавателя
     */
    public function getTodayForTeacher(int $userId): array
    {
        $user = User::findOrFail($userId);
        $today = Carbon::today();
        $now = Carbon::now();

        $result = [
            'lessons_today' => [],
            'unchecked_submissions' => [],
            'lessons_without_grades' => [],
            'students_with_red_risk' => [],
        ];

        // Пары сегодня
        $scheduleItems = ScheduleItem::where('teacher_user_id', $userId)
            ->where('date', $today)
            ->with(['subject', 'room', 'timeSlot', 'group'])
            ->orderBy('time_slot_id')
            ->get();

        foreach ($scheduleItems as $item) {
            $result['lessons_today'][] = [
                'id' => $item->id,
                'subject' => $item->subject->name ?? null,
                'room' => $item->room->name ?? null,
                'time' => $item->timeSlot ? ($item->timeSlot->start_time . ' - ' . $item->timeSlot->end_time) : null,
                'group' => $item->group->name ?? null,
            ];
        }

        // Непроверенные submissions
        // Поскольку модели Submission нет, используем ContestSubmission как пример
        // В реальной системе нужно использовать модель AssignmentSubmission
        $uncheckedSubmissions = DB::table('contest_submissions')
            ->join('contests', 'contest_submissions.contest_id', '=', 'contests.id')
            ->where('contests.created_by', $userId)
            ->whereNull('contest_submissions.graded_at')
            ->select('contest_submissions.*')
            ->get();

        // Альтернативно: проверяем задания без оценок
        $assignmentsWithoutGrades = Assignment::where('teacher_user_id', $userId)
            ->whereHas('targets')
            ->whereDoesntHave('grades')
            ->with(['subject'])
            ->get();

        foreach ($assignmentsWithoutGrades as $assignment) {
            $result['unchecked_submissions'][] = [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'subject' => $assignment->subject->name ?? null,
                'due_at' => $assignment->due_at?->format('Y-m-d H:i:s'),
            ];
        }

        // Уроки без оценок
        $lessonsWithoutGrades = Lesson::whereHas('scheduleItem', function ($q) use ($userId) {
            $q->where('teacher_user_id', $userId);
        })
            ->where('date', '<=', $today)
            ->whereDoesntHave('grades')
            ->with(['scheduleItem.subject', 'scheduleItem.group'])
            ->get();

        foreach ($lessonsWithoutGrades as $lesson) {
            $result['lessons_without_grades'][] = [
                'id' => $lesson->id,
                'date' => $lesson->date->format('Y-m-d'),
                'topic' => $lesson->topic,
                'subject' => $lesson->scheduleItem->subject->name ?? null,
                'group' => $lesson->scheduleItem->group->name ?? null,
            ];
        }

        // Студенты с red risk из групп преподавателя
        $teacherGroupIds = ScheduleItem::where('teacher_user_id', $userId)
            ->distinct()
            ->pluck('group_id')
            ->toArray();

        $redRiskStudents = Risk::where('level', 'red')
            ->whereHas('user', function ($q) use ($teacherGroupIds) {
                $q->whereHas('groups', function ($q2) use ($teacherGroupIds) {
                    $q2->whereIn('groups.id', $teacherGroupIds);
                });
            })
            ->with(['user'])
            ->get()
            ->unique('user_id')
            ->values();

        foreach ($redRiskStudents as $risk) {
            $result['students_with_red_risk'][] = [
                'id' => $risk->user_id,
                'fio' => $risk->user->fio ?? null,
                'risk_type' => $risk->risk_type,
                'score' => $risk->score,
            ];
        }

        return $result;
    }

    /**
     * Получить задачи на сегодня для куратора
     */
    public function getTodayForCurator(int $userId): array
    {
        $user = User::findOrFail($userId);
        $today = Carbon::today();
        $now = Carbon::now();

        $result = [
            'attendance_today' => [],
            'new_risks' => [],
            'group_overdue_assignments' => [],
            'low_average_students' => [],
        ];

        // Получаем группы куратора
        $curatorGroups = $user->groups()
            ->wherePivot('role_in_group', 'curator')
            ->get();

        if ($curatorGroups->isEmpty()) {
            return $result;
        }

        $groupIds = $curatorGroups->pluck('id')->toArray();

        // Посещаемость сегодня
        // TODO: Реализовать, когда будет модель Attendance
        // Пока возвращаем пустой массив
        $result['attendance_today'] = [];

        // Получаем ID студентов из групп куратора
        $studentIds = DB::table('group_members')
            ->whereIn('group_id', $groupIds)
            ->where('role_in_group', '!=', 'curator')
            ->pluck('user_id')
            ->toArray();

        // Новые риски (за последние 7 дней)
        $newRisks = Risk::whereIn('user_id', $studentIds)
            ->where('calculated_at', '>=', $today->copy()->subDays(7))
            ->whereIn('level', ['red', 'yellow'])
            ->with(['user'])
            ->get();

        foreach ($newRisks as $risk) {
            $result['new_risks'][] = [
                'id' => $risk->id,
                'student_id' => $risk->user_id,
                'student_fio' => $risk->user->fio ?? null,
                'level' => $risk->level,
                'risk_type' => $risk->risk_type,
                'calculated_at' => $risk->calculated_at?->format('Y-m-d H:i:s'),
            ];
        }

        // Долги по группе (просроченные задания)
        $groupOverdue = Assignment::whereHas('targets', function ($q) use ($groupIds) {
            $q->whereIn('group_id', $groupIds);
        })
            ->where('due_at', '<', $now)
            ->with(['subject', 'teacher', 'targets'])
            ->get();

        // Группируем по группам
        $groupedByGroup = [];
        foreach ($groupOverdue as $assignment) {
            foreach ($assignment->targets as $target) {
                if (in_array($target->group_id, $groupIds)) {
                    if (!isset($groupedByGroup[$target->group_id])) {
                        $groupedByGroup[$target->group_id] = [];
                    }
                    $groupedByGroup[$target->group_id][] = $assignment;
                    break; // Одно задание может быть в нескольких группах, но считаем один раз
                }
            }
        }

        foreach ($groupedByGroup as $groupId => $assignments) {
            $uniqueAssignments = collect($assignments)->unique('id')->values();
            $result['group_overdue_assignments'][] = [
                'group_id' => $groupId,
                'count' => $uniqueAssignments->count(),
                'assignments' => $uniqueAssignments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'title' => $a->title,
                        'subject' => $a->subject->name ?? null,
                    ];
                })->toArray(),
            ];
        }

        // Студенты с средним баллом <= 2
        $lowAverageStudents = DB::table('grades')
            ->join('group_members', 'grades.student_user_id', '=', 'group_members.user_id')
            ->whereIn('group_members.group_id', $groupIds)
            ->select('grades.student_user_id', DB::raw('AVG(grades.value) as avg_grade'))
            ->groupBy('grades.student_user_id')
            ->havingRaw('AVG(grades.value) <= 2')
            ->get();

        foreach ($lowAverageStudents as $student) {
            $studentUser = User::find($student->student_user_id);
            $result['low_average_students'][] = [
                'id' => $student->student_user_id,
                'fio' => $studentUser->fio ?? null,
                'average_grade' => round($student->avg_grade, 2),
            ];
        }

        return $result;
    }

    /**
     * Получить задачи на сегодня для администратора
     */
    public function getTodayForAdmin(int $userId): array
    {
        $now = Carbon::now();

        $result = [
            'documents_pending_approval' => [],
            'tickets_sla_overdue' => [],
            'schedule_conflicts' => [],
            'system_alerts' => [],
        ];

        // Документы на согласовании
        $pendingDocuments = Document::whereHas('routes', function ($q) {
            $q->where('status', 'pending');
        })
            ->where('status', '!=', 'approved')
            ->with(['creator', 'routes'])
            ->get();

        foreach ($pendingDocuments as $document) {
            $result['documents_pending_approval'][] = [
                'id' => $document->id,
                'type' => $document->type,
                'number' => $document->number,
                'date' => $document->date->format('Y-m-d'),
                'created_by' => $document->creator->fio ?? null,
                'pending_routes' => $document->routes->where('status', 'pending')->count(),
            ];
        }

        // Тикеты SLA overdue
        $overdueTickets = Ticket::where('status', '!=', 'closed')
            ->where(function ($q) use ($now) {
                $q->where('sla_due_at', '<', $now)
                    ->orWhere('resolution_due_at', '<', $now)
                    ->orWhere('first_response_due_at', '<', $now);
            })
            ->with(['creator', 'assignedTo'])
            ->get();

        foreach ($overdueTickets as $ticket) {
            $result['tickets_sla_overdue'][] = [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'sla_due_at' => $ticket->sla_due_at?->format('Y-m-d H:i:s'),
                'resolution_due_at' => $ticket->resolution_due_at?->format('Y-m-d H:i:s'),
                'created_by' => $ticket->creator->fio ?? null,
            ];
        }

        // Конфликты расписания
        // Проверяем пересечения по времени, преподавателю и аудитории
        $scheduleConflicts = DB::table('schedule_items as si1')
            ->join('schedule_items as si2', function ($join) {
                $join->on('si1.date', '=', 'si2.date')
                    ->on('si1.time_slot_id', '=', 'si2.time_slot_id')
                    ->whereColumn('si1.id', '!=', 'si2.id');
            })
            ->where(function ($q) {
                $q->whereColumn('si1.teacher_user_id', 'si2.teacher_user_id')
                    ->orWhereColumn('si1.room_id', 'si2.room_id');
            })
            ->select('si1.id as item1_id', 'si2.id as item2_id', 'si1.date', 'si1.teacher_user_id', 'si1.room_id')
            ->get();

        foreach ($scheduleConflicts as $conflict) {
            $result['schedule_conflicts'][] = [
                'item1_id' => $conflict->item1_id,
                'item2_id' => $conflict->item2_id,
                'date' => $conflict->date,
                'type' => $conflict->teacher_user_id ? 'teacher' : 'room',
            ];
        }

        // System alerts (упрощенная версия)
        // Можно расширить для проверки различных системных проблем
        $systemAlerts = [];

        // Проверка на неактивных пользователей
        $inactiveUsers = User::where('status', '!=', 'active')
            ->where('updated_at', '<', $now->copy()->subDays(30))
            ->count();

        if ($inactiveUsers > 0) {
            $systemAlerts[] = [
                'type' => 'inactive_users',
                'message' => "Найдено {$inactiveUsers} неактивных пользователей",
                'severity' => 'medium',
            ];
        }

        // Проверка на просроченные документы
        $expiredDocuments = Document::where('status', 'pending')
            ->where('created_at', '<', $now->copy()->subDays(30))
            ->count();

        if ($expiredDocuments > 0) {
            $systemAlerts[] = [
                'type' => 'expired_documents',
                'message' => "Найдено {$expiredDocuments} документов в статусе pending более 30 дней",
                'severity' => 'high',
            ];
        }

        $result['system_alerts'] = $systemAlerts;

        return $result;
    }
}

