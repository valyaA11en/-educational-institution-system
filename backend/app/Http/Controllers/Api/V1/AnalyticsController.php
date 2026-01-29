<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Risk;
use App\Services\RiskAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    protected RiskAnalyticsService $riskAnalytics;

    public function __construct(RiskAnalyticsService $riskAnalytics)
    {
        $this->riskAnalytics = $riskAnalytics;
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function risks(Request $request): JsonResponse
    {
        $query = Risk::with(['entity', 'resolver', 'calculator']);

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->has('risk_level')) {
            $query->byLevel($request->risk_level);
        }
        if ($request->has('active')) {
            if ($request->active) {
                $query->active();
            } else {
                $query->resolved();
            }
        }

        $risks = $query->orderBy('risk_score', 'desc')->paginate($request->get('per_page', 15));
        return response()->json($risks);
    }

    public function myRisks(Request $request): JsonResponse
    {
        $userId = Auth::id();
        
        // Get risks for entities owned by current user
        $risks = Risk::where('entity_type', 'user')
            ->where('entity_id', $userId)
            ->active()
            ->orderBy('risk_score', 'desc')
            ->get();

        return response()->json(['data' => $risks]);
    }

    public function studentRisk(Request $request, $studentId): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $analysis = $this->riskAnalytics->analyzeEntity('user', $studentId, $tenantId);
        return response()->json(['data' => $analysis]);
    }

    public function groupRisk(Request $request, $groupId): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $analysis = $this->riskAnalytics->analyzeEntity('group', $groupId, $tenantId);
        return response()->json(['data' => $analysis]);
    }

    public function risksReport(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $startDate = $request->has('start_date') 
            ? Carbon::parse($request->start_date) 
            : Carbon::now()->subMonths(3);
        
        $endDate = $request->has('end_date') 
            ? Carbon::parse($request->end_date) 
            : Carbon::now();

        $report = $this->riskAnalytics->generateReport($tenantId, $startDate, $endDate);
        return response()->json(['data' => $report]);
    }

    public function riskTrends(Request $request): JsonResponse
    {
        $startDate = $request->has('start_date') 
            ? Carbon::parse($request->start_date) 
            : null;
        
        $endDate = $request->has('end_date') 
            ? Carbon::parse($request->end_date) 
            : null;

        $trends = $this->riskAnalytics->getRiskTrends(null, $startDate, $endDate);
        return response()->json(['data' => $trends]);
    }

    public function riskDistribution(Request $request): JsonResponse
    {
        $distribution = $this->riskAnalytics->getRiskDistribution(null);
        return response()->json(['data' => $distribution]);
    }

    public function entityRisks(Request $request, $entityType, $entityId): JsonResponse
    {
        $analysis = $this->riskAnalytics->analyzeEntity($entityType, $entityId, null);
        return response()->json(['data' => $analysis]);
    }

    public function resolveRisk(Request $request, $riskId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resolution_notes' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $risk = $this->riskAnalytics->resolveRisk(
            $riskId,
            Auth::id(),
            $request->resolution_notes
        );

        return response()->json(['data' => $risk->load('resolver')]);
    }

    public function exportRisksReport(Request $request): JsonResponse
    {
        // Placeholder for export functionality
        return response()->json(['message' => 'Export functionality will be implemented with export service']);
    }

    public function topics(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $q = \Illuminate\Support\Facades\DB::table('topic_performance_stats')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('fail_percent');
        if ($request->filled('subject_id')) {
            $q->where('subject_id', $request->subject_id);
        }
        if ($request->filled('term_id')) {
            $q->where('term_id', $request->term_id);
        }
        $items = $q->limit(200)->get();
        return response()->json(['data' => $items]);
    }

    public function topicPerformance(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $q = \Illuminate\Support\Facades\DB::table('topic_performance_stats')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('fail_percent');
        if ($request->filled('subject_id')) {
            $q->where('subject_id', $request->subject_id);
        }
        if ($request->filled('term_id')) {
            $q->where('term_id', $request->term_id);
        }
        $items = $q->limit(500)->get();
        return response()->json(['data' => $items]);
    }

    public function topicStudents(Request $request, $statId): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $stat = \Illuminate\Support\Facades\DB::table('topic_performance_stats')
            ->where('id', $statId)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$stat) {
            return response()->json(['message' => 'Topic stat not found'], 404);
        }

        $rows = \Illuminate\Support\Facades\DB::table('failed_topics_analysis as f')
            ->leftJoin('users as u', 'u.id', '=', 'f.student_user_id')
            ->where('f.tenant_id', $tenantId)
            ->where('f.subject_id', $stat->subject_id)
            ->where('f.ktp_topic_id', $stat->ktp_topic_id)
            ->select('f.id', 'f.student_user_id', 'f.topic_name', 'f.failed_attempts', 'f.average_grade', 'f.last_attempt_date', 'u.fio')
            ->orderByDesc('f.failed_attempts')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function recalc(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}