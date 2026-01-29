<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\RiskCalculator;
use App\Services\Analytics\RiskAnalyticsService;
use App\Models\Risk;
use App\Support\Security\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __construct(
        private RiskCalculator $riskCalculator,
        private RiskAnalyticsService $riskAnalyticsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'entity_type' => $request->query('entity_type'),
            'risk_level' => $request->query('risk_level'),
            'min_score' => $request->query('min_score') ? (float) $request->query('min_score') : null,
        ];

        $report = $this->riskAnalyticsService->getRiskReport(
            $request->query('term_id') ? (int) $request->query('term_id') : null,
            array_filter($filters)
        );

        return response()->json($report);
    }

    public function studentRisk(int $studentId, Request $request): JsonResponse
    {
        $termId = $request->query('term_id') ? (int) $request->query('term_id') : null;
        $save = $request->boolean('save', false);

        if ($save) {
            $risk = $this->riskAnalyticsService->calculateAndSaveStudentRisk(
                $studentId,
                $termId,
                auth()->id()
            );
            return response()->json($risk);
        }

        $risk = $this->riskCalculator->calculateStudentRisk($studentId, $termId);
        return response()->json($risk);
    }

    public function groupRisk(int $groupId, Request $request): JsonResponse
    {
        $termId = $request->query('term_id') ? (int) $request->query('term_id') : null;
        $save = $request->boolean('save', false);

        if ($save) {
            $risk = $this->riskAnalyticsService->calculateAndSaveGroupRisk(
                $groupId,
                $termId,
                auth()->id()
            );
            return response()->json($risk);
        }

        $risk = $this->riskCalculator->calculateGroupRisk($groupId, $termId);
        return response()->json($risk);
    }

    public function risksReport(Request $request): JsonResponse
    {
        $termId = $request->query('term_id') ? (int) $request->query('term_id') : null;
        $recalculate = $request->boolean('recalculate', false);

        if ($recalculate) {
            $this->riskAnalyticsService->calculateRisksForAllGroups($termId, auth()->id());
        }

        $report = $this->riskAnalyticsService->getRiskReport($termId);
        return response()->json($report);
    }

    public function riskTrends(Request $request): JsonResponse
    {
        $termId = $request->query('term_id') ? (int) $request->query('term_id') : null;
        $days = $request->integer('days', 30);

        $trends = $this->riskAnalyticsService->getRiskTrends($termId, $days);
        return response()->json($trends);
    }

    public function riskDistribution(Request $request): JsonResponse
    {
        $termId = $request->query('term_id') ? (int) $request->query('term_id') : null;

        $distribution = $this->riskAnalyticsService->getRiskDistribution($termId);
        return response()->json($distribution);
    }

    public function entityRisks(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $termId = $request->query('term_id') ? (int) $request->query('term_id') : null;

        $risks = $this->riskAnalyticsService->getEntityRisks($entityType, $entityId, $termId);
        return response()->json($risks);
    }

    public function resolveRisk(Request $request, int $riskId): JsonResponse
    {
        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $risk = $this->riskAnalyticsService->resolveRisk(
            $riskId,
            $request->input('notes'),
            auth()->id()
        );

        return response()->json($risk);
    }

    public function exportRisksReport(Request $request)
    {
        $this->authorize('viewAny', Risk::class);

        $groupId = $request->query('groupId') ? (int) $request->query('groupId') : null;
        $level = $request->query('level');
        $riskType = $request->query('risk_type');

        $export = new \App\Exports\RisksExport($groupId, $level, $riskType);
        
        $filename = 'risks_export_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Get risks for curator/methodist/management/admin
     */
    public function risks(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Risk::class);

        $query = Risk::with('user')
            ->whereNotNull('user_id');

        // Filter by group
        if ($groupId = $request->query('groupId')) {
            $studentIds = DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('role_in_group', 'student')
                ->pluck('user_id');
            
            $query->whereIn('user_id', $studentIds);
        }

        // Filter by level
        if ($level = $request->query('level')) {
            $query->where('level', $level);
        }

        // Filter by term
        if ($termId = $request->query('term_id')) {
            $query->where('term_id', $termId);
        } else {
            // Default to current term
            $currentTermId = DB::table('terms')->where('is_current', true)->value('id');
            if ($currentTermId) {
                $query->where('term_id', $currentTermId);
            }
        }

        // Filter by risk type
        if ($riskType = $request->query('risk_type')) {
            $query->where('risk_type', $riskType);
        }

        $risks = $query->orderBy('score', 'desc')
            ->orderBy('calculated_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($risks);
    }

    /**
     * Get my risks (for student/parent)
     */
    public function myRisks(Request $request): JsonResponse
    {
        $user = auth()->user();
        $accessScope = app(AccessScopeService::class);
        
        $studentIds = [];
        
        // If user is a student, get their own ID
        if ($accessScope->isStudent($user->id)) {
            $studentIds[] = $user->id;
        }
        
        // If user is a parent, get children IDs
        // Also support filtering by specific child_id for parent view
        if ($accessScope->isParent($user->id)) {
            $childrenIds = $accessScope->childrenStudentIds($user->id);
            
            // If child_id is specified, filter to that child only
            if ($childId = $request->query('child_id')) {
                $childId = (int) $childId;
                if (in_array($childId, $childrenIds)) {
                    $studentIds = [$childId];
                } else {
                    // Parent doesn't have access to this child
                    return response()->json(['data' => [], 'message' => 'Access denied']);
                }
            } else {
                $studentIds = array_merge($studentIds, $childrenIds);
            }
        }

        if (empty($studentIds)) {
            return response()->json(['data' => [], 'message' => 'No student access']);
        }

        $query = Risk::with('user')
            ->whereIn('user_id', $studentIds);

        // Filter by term
        if ($termId = $request->query('term_id')) {
            $query->where('term_id', $termId);
        } else {
            // Default to current term
            $currentTermId = DB::table('terms')->where('is_current', true)->value('id');
            if ($currentTermId) {
                $query->where('term_id', $currentTermId);
            }
        }

        // Filter by level
        if ($level = $request->query('level')) {
            $query->where('level', $level);
        }

        $risks = $query->orderBy('score', 'desc')
            ->orderBy('calculated_at', 'desc')
            ->get();

        return response()->json(['data' => $risks]);
    }

    /**
     * Manual recalc (admin only)
     */
    public function recalc(Request $request): JsonResponse
    {
        $this->authorize('manage', Risk::class);

        $termId = $request->query('term_id');

        try {
            $command = 'analytics:recalc-risks';
            if ($termId) {
                $command .= ' --term-id=' . $termId;
            }

            Artisan::call($command);

            return response()->json([
                'message' => 'Risk recalculation started',
                'output' => Artisan::output(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to recalculate risks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/analytics/topics
     * Get topic performance statistics
     */
    public function topics(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Проверка прав: методист, руководство, admin
        if (!$user->hasRole('методист') && !$user->hasRole('methodist') 
            && !$user->hasRole('admin') 
            && !$user->hasRole('руководство') && !$user->hasRole('management')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = \App\Models\TopicPerformanceStat::with(['subject', 'topic'])
            ->where('tenant_id', app('tenant_id'));

        // Фильтр по предмету
        if ($subjectId = $request->query('subjectId')) {
            $query->where('subject_id', $subjectId);
        }

        // Фильтр по периоду
        if ($termId = $request->query('termId')) {
            $query->where('term_id', $termId);
        }

        // Фильтр по проценту неуспевающих
        if ($failPercentMin = $request->query('fail_percent')) {
            $query->where('fail_percent', '>=', (float) $failPercentMin);
        }

        $stats = $query->orderBy('fail_percent', 'desc')
            ->orderBy('calculated_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($stats);
    }

    /**
     * GET /api/analytics/topics/{statId}/students
     * Get students list for a topic (drill-down)
     */
    public function topicStudents(int $statId, Request $request): JsonResponse
    {
        $user = auth()->user();

        // Проверка прав: методист, руководство, admin
        if (!$user->hasRole('методист') && !$user->hasRole('methodist') 
            && !$user->hasRole('admin') 
            && !$user->hasRole('руководство') && !$user->hasRole('management')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $stat = \App\Models\TopicPerformanceStat::findOrFail($statId);

        // Получаем оценки по теме и группируем по студентам
        $gradesQuery = Grade::query()
            ->join('lessons', 'grades.lesson_id', '=', 'lessons.id')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->join('users', 'grades.student_user_id', '=', 'users.id')
            ->where('lessons.ktp_topic_id', $stat->ktp_topic_id)
            ->where('schedule_items.subject_id', $stat->subject_id)
            ->where('schedule_items.tenant_id', app('tenant_id'))
            ->whereNotNull('grades.student_user_id')
            ->select([
                'grades.student_user_id',
                'users.fio',
                'grades.value',
            ]);

        // Фильтр по периоду
        if ($stat->term_id) {
            $gradesQuery->join('schedule_versions', 'schedule_items.version_id', '=', 'schedule_versions.id')
                ->where('schedule_versions.term_id', $stat->term_id);
        }

        $grades = $gradesQuery->get();

        // Группируем по студентам и считаем средний балл
        $studentGrades = $grades->groupBy('student_user_id');
        $students = [];

        foreach ($studentGrades as $studentId => $studentGradeList) {
            $avg = $studentGradeList->avg('value');
            $firstGrade = $studentGradeList->first();
            
            $students[] = [
                'id' => $studentId,
                'fio' => $firstGrade->fio ?? '',
                'avg_grade' => round($avg, 2),
                'grades_count' => $studentGradeList->count(),
            ];
        }

        // Сортируем по среднему баллу (asc - неуспевающие в начале)
        usort($students, function ($a, $b) {
            return $a['avg_grade'] <=> $b['avg_grade'];
        });

        return response()->json(['data' => $students]);
    }
}
