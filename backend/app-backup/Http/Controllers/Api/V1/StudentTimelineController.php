<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StudentTimelineService;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentTimelineController extends Controller
{
    public function __construct(
        private StudentTimelineService $timelineService
    ) {}

    public function index(int $studentId, Request $request): JsonResponse
    {
        $user = Auth::user();

        // Проверка прав доступа
        if (!$this->canViewTimeline($user, $studentId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $filters = [];
        
        if ($request->has('dateFrom')) {
            $filters['dateFrom'] = $request->input('dateFrom');
        }

        if ($request->has('dateTo')) {
            $filters['dateTo'] = $request->input('dateTo');
        }

        if ($request->has('event_type')) {
            $eventTypes = $request->input('event_type');
            if (is_string($eventTypes)) {
                $eventTypes = explode(',', $eventTypes);
            }
            if (is_array($eventTypes)) {
                $filters['event_type'] = array_filter($eventTypes);
            }
        }

        $timeline = $this->timelineService->getTimeline($studentId, $filters);

        return response()->json(['data' => $timeline]);
    }

    public function export(int $studentId, Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();

        // Проверка прав доступа
        if (!$this->canViewTimeline($user, $studentId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $filters = [];
        
        if ($request->has('dateFrom')) {
            $filters['dateFrom'] = $request->input('dateFrom');
        }

        if ($request->has('dateTo')) {
            $filters['dateTo'] = $request->input('dateTo');
        }

        if ($request->has('event_type')) {
            $eventTypes = $request->input('event_type');
            if (is_string($eventTypes)) {
                $eventTypes = explode(',', $eventTypes);
            }
            if (is_array($eventTypes)) {
                $filters['event_type'] = array_filter($eventTypes);
            }
        }

        $timeline = $this->timelineService->getTimeline($studentId, $filters);
        $student = \App\Models\User::findOrFail($studentId);
        $groups = $student->groups()->get();
        $tenant = app('tenant') ?? \App\Models\Tenant::first();

        // Группировка по месяцам
        $groupedByMonth = [];
        foreach ($timeline as $event) {
            $date = \Carbon\Carbon::createFromFormat('Y-m-d', $event['event_date']);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->locale('ru')->translatedFormat('F Y');
            
            if (!isset($groupedByMonth[$monthKey])) {
                $groupedByMonth[$monthKey] = [
                    'label' => $monthLabel,
                    'events' => [],
                ];
            }
            
            $groupedByMonth[$monthKey]['events'][] = $event;
        }

        $data = [
            'tenant' => $tenant,
            'student' => $student,
            'groups' => $groups,
            'timeline' => $groupedByMonth,
            'filters' => $filters,
            'export_date' => now(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('print.student_timeline', $data);
        $filename = sprintf('timeline_%s_%s.pdf', $studentId, now()->format('Y-m-d'));

        return $pdf->download($filename);
    }

    /**
     * Проверка прав на просмотр timeline
     */
    protected function canViewTimeline($user, int $studentId): bool
    {
        // Админ и методист могут видеть всё
        if ($user->hasRole('admin') || $user->hasRole('методист')) {
            return true;
        }

        // Студент может видеть только свой timeline
        if ($user->id === $studentId) {
            $isStudent = $user->hasRole('student') || $user->hasRole('студент');
            if ($isStudent) {
                return true;
            }
        }

        // Родитель может видеть timeline своих детей
        // TODO: Реализовать связь родитель-ребёнок в модели User
        // if ($user->hasRole('родитель') || $user->hasRole('parent')) {
        //     $children = $user->children()->pluck('id')->toArray();
        //     if (in_array($studentId, $children)) {
        //         return true;
        //     }
        // }

        // Куратор может видеть timeline студентов своей группы
        if ($user->hasRole('преподаватель') || $user->hasRole('teacher')) {
            $student = User::find($studentId);
            if ($student) {
                $studentGroups = $student->groups()->pluck('groups.id')->toArray();
                $userGroups = $user->groups()->wherePivot('role_in_group', 'curator')->pluck('groups.id')->toArray();
                
                if (count(array_intersect($studentGroups, $userGroups)) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
