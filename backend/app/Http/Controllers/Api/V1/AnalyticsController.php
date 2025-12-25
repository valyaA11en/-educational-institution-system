<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\RiskCalculator;
use App\Services\Analytics\RiskAnalyticsService;
use App\Models\Risk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function exportRisksReport(Request $request): JsonResponse
    {
        // TODO: export to XLSX/PDF
        return response()->json(['message' => 'Export not implemented yet']);
    }
}
