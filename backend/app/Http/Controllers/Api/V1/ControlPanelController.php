<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use App\Models\Grade;
// use App\Models\Attendance; // TODO: Add Attendance model if needed
use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ControlPanelController extends Controller
{
    public function curator(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Только куратор (преподаватель с кураторством группы) или админ
        if (!$user->hasRole('преподаватель') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $groupId = $request->input('group_id');
        if (!$groupId) {
            return response()->json(['message' => 'group_id required'], 400);
        }

        $group = Group::findOrFail($groupId);

        // Статистика группы
        $students = $group->members()->wherePivot('role_in_group', 'student')->get();
        $studentsCount = $students->count();

        // Средний балл группы
        $avgGrade = Grade::whereIn('student_user_id', $students->pluck('id'))
            ->where('created_at', '>=', Carbon::now()->subMonth())
            ->avg('value');

        // Посещаемость за месяц (TODO: Add Attendance model)
        $attendanceRate = 0;
        if (class_exists(\App\Models\Attendance::class)) {
            $attendanceRate = \App\Models\Attendance::whereIn('student_user_id', $students->pluck('id'))
                ->where('date', '>=', Carbon::now()->subMonth())
                ->selectRaw('COUNT(*) FILTER (WHERE status = \'present\') * 100.0 / COUNT(*) as rate')
                ->value('rate') ?? 0;
        }

        // Студенты с низкой успеваемостью
        $lowPerformers = User::whereIn('id', $students->pluck('id'))
            ->withCount(['grades as avg_grade' => function ($q) {
                $q->select(DB::raw('AVG(value)'))
                    ->where('created_at', '>=', Carbon::now()->subMonth());
            }])
            ->having('avg_grade', '<', 3.5)
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'fio' => $student->fio,
                    'avg_grade' => round((float) $student->avg_grade, 2),
                ];
            });

        // Студенты с низкой посещаемостью (TODO: Add Attendance model)
        $lowAttendance = collect([]);
        if (class_exists(\App\Models\Attendance::class)) {
            $lowAttendance = User::whereIn('id', $students->pluck('id'))
                ->withCount(['attendances as attendance_rate' => function ($q) {
                    $q->selectRaw('COUNT(*) FILTER (WHERE status = \'present\') * 100.0 / COUNT(*)')
                        ->where('date', '>=', Carbon::now()->subMonth());
                }])
                ->having('attendance_rate', '<', 70)
                ->get()
                ->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'fio' => $student->fio,
                        'attendance_rate' => round((float) $student->attendance_rate, 2),
                    ];
                });
        }

        return response()->json([
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
            ],
            'statistics' => [
                'students_count' => $studentsCount,
                'average_grade' => round((float) $avgGrade, 2),
                'attendance_rate' => round((float) $attendanceRate, 2),
            ],
            'low_performers' => $lowPerformers,
            'low_attendance' => $lowAttendance,
        ]);
    }

    public function methodist(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->hasRole('методист') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Статистика по всем группам
        $groups = Group::withCount(['members' => function ($q) {
            $q->wherePivot('role_in_group', 'student');
        }])->get();

        // Общая статистика успеваемости
        $totalStudents = User::whereHas('roles', function ($q) {
            $q->where('name', 'student');
        })->count();

        $avgGradeAll = Grade::where('created_at', '>=', Carbon::now()->subMonth())
            ->avg('value');

        // Группы с низкой успеваемостью
        $lowPerformingGroups = Group::with(['members' => function ($q) {
            $q->wherePivot('role_in_group', 'student');
        }])
            ->get()
            ->map(function ($group) {
                $studentIds = $group->members->pluck('id');
                $avgGrade = Grade::whereIn('student_user_id', $studentIds)
                    ->where('created_at', '>=', Carbon::now()->subMonth())
                    ->avg('value');

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'students_count' => $group->members->count(),
                    'average_grade' => round((float) $avgGrade, 2),
                ];
            })
            ->filter(function ($group) {
                return $group['average_grade'] < 3.5;
            })
            ->values();

        // Неопубликованные расписания
        $draftSchedules = \App\Models\ScheduleVersion::where('status', 'draft')->count();

        return response()->json([
            'statistics' => [
                'total_students' => $totalStudents,
                'total_groups' => $groups->count(),
                'average_grade_all' => round((float) $avgGradeAll, 2),
            ],
            'low_performing_groups' => $lowPerformingGroups,
            'draft_schedules' => $draftSchedules,
        ]);
    }

    public function headmaster(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Общая статистика по учреждению
        $totalStudents = User::whereHas('roles', function ($q) {
            $q->where('name', 'student');
        })->count();

        $totalTeachers = User::whereHas('roles', function ($q) {
            $q->where('name', 'преподаватель');
        })->count();

        $totalGroups = Group::count();

        // Успеваемость
        $avgGrade = Grade::where('created_at', '>=', Carbon::now()->subMonth())
            ->avg('value');

        // Посещаемость (TODO: Add Attendance model)
        $attendanceRate = 0;
        if (class_exists(\App\Models\Attendance::class)) {
            $attendanceRate = \App\Models\Attendance::where('date', '>=', Carbon::now()->subMonth())
                ->selectRaw('COUNT(*) FILTER (WHERE status = \'present\') * 100.0 / COUNT(*) as rate')
                ->value('rate') ?? 0;
        }

        // Активные задания
        $activeAssignments = Assignment::where('due_at', '>=', Carbon::now())
            ->count();

        // Непроверенные решения (TODO: Add Submission model)
        $pendingSubmissions = 0;
        if (class_exists(\App\Models\Submission::class)) {
            $pendingSubmissions = \App\Models\Submission::where('status', 'submitted')->count();
        }

        // Открытые тикеты
        $openTickets = \App\Models\Ticket::where('status', 'open')->count();

        return response()->json([
            'statistics' => [
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'total_groups' => $totalGroups,
                'average_grade' => round((float) $avgGrade, 2),
                'attendance_rate' => round((float) $attendanceRate, 2),
            ],
            'tasks' => [
                'active_assignments' => $activeAssignments,
                'pending_submissions' => $pendingSubmissions,
                'open_tickets' => $openTickets,
            ],
        ]);
    }
}

