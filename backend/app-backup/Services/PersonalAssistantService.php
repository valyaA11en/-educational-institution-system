<?php

namespace App\Services;

use App\Models\User;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\Lesson;
use App\Models\ScheduleItem;
use App\Models\Document;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class PersonalAssistantService
{
    public function getTodayTasks(User $user): array
    {
        $tasks = [];

        // Проверка роли студента (может быть 'student' или 'студент')
        $isStudent = $user->hasRole('student') || $user->hasRole('студент');
        if ($isStudent) {
            $tasks = array_merge($tasks, $this->getStudentTasks($user));
        }

        if ($user->hasRole('преподаватель') || $user->hasRole('teacher')) {
            $tasks = array_merge($tasks, $this->getTeacherTasks($user));
        }

        if ($user->hasRole('методист') || $user->hasRole('methodist')) {
            $tasks = array_merge($tasks, $this->getMethodistTasks($user));
        }

        if ($user->hasRole('admin')) {
            $tasks = array_merge($tasks, $this->getAdminTasks($user));
        }

        // Сортировка по приоритету и времени
        usort($tasks, function ($a, $b) {
            $priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            $priorityDiff = ($priorityOrder[$a['priority']] ?? 3) - ($priorityOrder[$b['priority']] ?? 3);
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }
            return strtotime($a['due_at'] ?? '9999-12-31') - strtotime($b['due_at'] ?? '9999-12-31');
        });

        return $tasks;
    }

    protected function getStudentTasks(User $user): array
    {
        $tasks = [];
        $today = Carbon::today();

        // Задания с дедлайном сегодня
        $assignments = Assignment::whereHas('targets', function ($q) use ($user) {
            $q->where('target_user_id', $user->id);
        })
            ->where('due_at', '>=', $today->startOfDay())
            ->where('due_at', '<=', $today->copy()->endOfDay())
            ->get();

        foreach ($assignments as $assignment) {
            $submission = null;
            if (class_exists(\App\Models\Submission::class)) {
                $submission = \App\Models\Submission::where('assignment_id', $assignment->id)
                    ->where('student_user_id', $user->id)
                    ->first();
            }

            if (!$submission || $submission->status === 'draft') {
                $tasks[] = [
                    'type' => 'assignment_due',
                    'title' => "Сдать задание: {$assignment->title}",
                    'description' => "Дедлайн: {$assignment->due_at->format('H:i')}",
                    'priority' => 'urgent',
                    'due_at' => $assignment->due_at->format('Y-m-d H:i'),
                    'action_url' => "/assignments/{$assignment->id}",
                    'entity_type' => 'Assignment',
                    'entity_id' => $assignment->id,
                ];
            }
        }

        // Задания с дедлайном в ближайшие 3 дня
        $upcomingAssignments = Assignment::whereHas('targets', function ($q) use ($user) {
            $q->where('target_user_id', $user->id);
        })
            ->where('due_at', '>', $today->copy()->endOfDay())
            ->where('due_at', '<=', $today->copy()->addDays(3)->endOfDay())
            ->get()
            ->filter(function ($assignment) use ($user) {
                if (!class_exists(\App\Models\Submission::class)) {
                    return true;
                }
                $submission = \App\Models\Submission::where('assignment_id', $assignment->id)
                    ->where('student_user_id', $user->id)
                    ->whereIn('status', ['submitted', 'graded'])
                    ->first();
                return !$submission;
            });

        foreach ($upcomingAssignments as $assignment) {
            $daysLeft = $today->diffInDays($assignment->due_at, false);
            $tasks[] = [
                'type' => 'assignment_upcoming',
                'title' => "Задание скоро: {$assignment->title}",
                'description' => "Осталось дней: {$daysLeft}",
                'priority' => $daysLeft <= 1 ? 'high' : 'medium',
                'due_at' => $assignment->due_at->format('Y-m-d H:i'),
                'action_url' => "/assignments/{$assignment->id}",
                'entity_type' => 'Assignment',
                'entity_id' => $assignment->id,
            ];
        }

        // Расписание на сегодня
        $scheduleItems = ScheduleItem::whereHas('scheduleVersion.group.members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->where('date', $today)
            ->whereHas('scheduleVersion', function ($q) {
                $q->where('status', 'published');
            })
            ->with(['subject', 'room', 'timeSlot'])
            ->orderBy('time_slot_id')
            ->get();

        if ($scheduleItems->isNotEmpty()) {
            $lessonsCount = $scheduleItems->count();
            $tasks[] = [
                'type' => 'schedule_today',
                'title' => "Расписание на сегодня",
                'description' => "Уроков: {$lessonsCount}",
                'priority' => 'medium',
                'action_url' => '/schedule',
                'entity_type' => 'Schedule',
                'entity_id' => null,
            ];
        }

        return $tasks;
    }

    protected function getTeacherTasks(User $user): array
    {
        $tasks = [];
        $today = Carbon::today();

        // Непроверенные решения (если модель существует)
        $pendingSubmissions = 0;
        if (class_exists(\App\Models\Submission::class)) {
            $pendingSubmissions = \App\Models\Submission::whereHas('assignment', function ($q) use ($user) {
                $q->where('teacher_user_id', $user->id);
            })
                ->where('status', 'submitted')
                ->count();
        }

        if ($pendingSubmissions > 0) {
            $tasks[] = [
                'type' => 'submissions_pending',
                'title' => "Непроверенные решения",
                'description' => "Ожидают проверки: {$pendingSubmissions}",
                'priority' => 'high',
                'action_url' => '/assignments?status=submitted',
                'entity_type' => 'Submission',
                'entity_id' => null,
            ];
        }

        // Уроки сегодня
        $lessonsToday = Lesson::whereHas('scheduleItem', function ($q) use ($user, $today) {
            $q->where('teacher_user_id', $user->id)
                ->where('date', $today);
        })
            ->count();

        if ($lessonsToday > 0) {
            $tasks[] = [
                'type' => 'lessons_today',
                'title' => "Уроки сегодня",
                'description' => "Уроков: {$lessonsToday}",
                'priority' => 'medium',
                'action_url' => '/schedule',
                'entity_type' => 'Lesson',
                'entity_id' => null,
            ];
        }

        // Документы на согласовании
        $pendingDocuments = Document::whereHas('routes', function ($q) use ($user) {
            $q->where('approver_user_id', $user->id)
                ->where('status', 'pending');
        })
            ->count();

        if ($pendingDocuments > 0) {
            $tasks[] = [
                'type' => 'documents_pending',
                'title' => "Документы на согласовании",
                'description' => "Ожидают: {$pendingDocuments}",
                'priority' => 'medium',
                'action_url' => '/documents?status=pending',
                'entity_type' => 'Document',
                'entity_id' => null,
            ];
        }

        return $tasks;
    }

    protected function getMethodistTasks(User $user): array
    {
        $tasks = [];

        // Неопубликованные версии расписания
        $draftSchedules = \App\Models\ScheduleVersion::where('status', 'draft')
            ->count();

        if ($draftSchedules > 0) {
            $tasks[] = [
                'type' => 'schedules_draft',
                'title' => "Черновики расписания",
                'description' => "Неопубликовано: {$draftSchedules}",
                'priority' => 'medium',
                'action_url' => '/schedule/versions?status=draft',
                'entity_type' => 'ScheduleVersion',
                'entity_id' => null,
            ];
        }

        // Документы на подписании
        $pendingDocuments = Document::where('status', 'pending_approval')
            ->count();

        if ($pendingDocuments > 0) {
            $tasks[] = [
                'type' => 'documents_pending_approval',
                'title' => "Документы на подписании",
                'description' => "Ожидают: {$pendingDocuments}",
                'priority' => 'high',
                'action_url' => '/documents?status=pending_approval',
                'entity_type' => 'Document',
                'entity_id' => null,
            ];
        }

        return $tasks;
    }

    protected function getAdminTasks(User $user): array
    {
        $tasks = [];

        // Тикеты без ответа
        $openTickets = \App\Models\Ticket::where('status', 'open')
            ->whereNull('assigned_to')
            ->count();

        if ($openTickets > 0) {
            $tasks[] = [
                'type' => 'tickets_open',
                'title' => "Открытые тикеты",
                'description' => "Без ответа: {$openTickets}",
                'priority' => 'medium',
                'action_url' => '/admin/tickets?status=open',
                'entity_type' => 'Ticket',
                'entity_id' => null,
            ];
        }

        return $tasks;
    }
}

