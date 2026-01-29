<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Grade;
// use App\Models\Attendance; // TODO: Add Attendance model if needed
use App\Models\Assignment;
// use App\Models\Submission; // TODO: Add Submission model if needed
use App\Models\PortfolioItem;
use App\Services\StudentTimelineService;
use App\Services\FailedTopicsAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentExportController extends Controller
{
    public function __construct(
        private StudentTimelineService $timelineService,
        private FailedTopicsAnalysisService $analysisService
    ) {}

    public function export(int $studentId, Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();

        // Проверка прав: студент может экспортировать только свои данные, преподаватель/админ - любого
        if ($studentId != $user->id && !$user->hasRole('admin') && !$user->hasRole('преподаватель')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $student = User::findOrFail($studentId);
        $format = $request->input('format', 'pdf');

        if ($format !== 'pdf') {
            return response()->json(['message' => 'Only PDF format supported'], 400);
        }

        // Сбор данных
        $data = $this->collectStudentData($student);

        // Генерация PDF
        $pdf = Pdf::loadView('print.student_export', $data);
        $filename = sprintf('student_%s_%s.pdf', $student->id, now()->format('Y-m-d'));

        return $pdf->download($filename);
    }

    protected function collectStudentData(User $student): array
    {
        $tenant = app('tenant');

        // Основная информация
        $groups = $student->groups()->get();
        
        // Оценки за последний год
        $grades = Grade::where('student_user_id', $student->id)
            ->where('created_at', '>=', now()->subYear())
            ->with(['lesson.scheduleItem.subject', 'assignment.subject'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Посещаемость за последний год (TODO: Add Attendance model)
        $attendance = collect([]);
        if (class_exists(\App\Models\Attendance::class)) {
            $attendance = \App\Models\Attendance::where('student_user_id', $student->id)
                ->where('date', '>=', now()->subYear())
                ->orderBy('date', 'desc')
                ->get();
        }

        // Задания
        $assignments = Assignment::whereHas('targets', function ($q) use ($student) {
            $q->where('target_user_id', $student->id);
        })
            ->with(['subject'])
            ->orderBy('due_at', 'desc')
            ->get()
            ->map(function ($assignment) use ($student) {
                $assignment->submissions = collect([]);
                if (class_exists(\App\Models\Submission::class)) {
                    $assignment->submissions = \App\Models\Submission::where('assignment_id', $assignment->id)
                        ->where('student_user_id', $student->id)
                        ->get();
                }
                return $assignment;
            });

        // Timeline
        $timeline = $this->timelineService->getTimeline($student->id);

        // Портфолио
        $portfolio = PortfolioItem::where('student_user_id', $student->id)
            ->orderBy('date', 'desc')
            ->get();

        // Проваленные темы
        $failedTopics = $this->analysisService->getFailedTopics($student->id);

        // Статистика
        $stats = [
            'total_grades' => $grades->count(),
            'average_grade' => round($grades->avg('value'), 2),
            'attendance_rate' => $attendance->count() > 0 
                ? round($attendance->where('status', 'present')->count() * 100 / max($attendance->count(), 1), 2)
                : 0,
            'completed_assignments' => $assignments->filter(function ($a) {
                return $a->submissions->where('status', 'graded')->isNotEmpty();
            })->count(),
            'total_assignments' => $assignments->count(),
        ];

        return [
            'tenant' => $tenant,
            'student' => $student,
            'groups' => $groups,
            'grades' => $grades,
            'attendance' => $attendance,
            'assignments' => $assignments,
            'timeline' => array_slice($timeline, 0, 50), // Последние 50 событий
            'portfolio' => $portfolio,
            'failed_topics' => $failedTopics,
            'statistics' => $stats,
            'export_date' => now(),
        ];
    }
}

