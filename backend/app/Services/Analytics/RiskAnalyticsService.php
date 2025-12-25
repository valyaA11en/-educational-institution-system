<?php

namespace App\Services\Analytics;

use App\Models\Risk;
use App\Models\User;
use App\Models\Group;
use App\Models\GradePeriodSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class RiskAnalyticsService
{
    public function __construct(
        private RiskCalculator $riskCalculator
    ) {
    }

    /**
     * Calculate and save risk for a student
     */
    public function calculateAndSaveStudentRisk(int $studentId, ?int $termId = null, ?int $calculatedBy = null): Risk
    {
        $termId = $termId ?? $this->getCurrentTermId();
        $riskData = $this->riskCalculator->calculateStudentRisk($studentId, $termId);

        // Calculate risk score
        $riskScore = $this->calculateRiskScore($riskData['factors'], $riskData['risk_level']);

        // Find or create risk record
        $risk = Risk::updateOrCreate(
            [
                'entity_type' => 'student',
                'entity_id' => $studentId,
                'term_id' => $termId,
            ],
            [
                'risk_level' => $riskData['risk_level'],
                'risk_score' => $riskScore,
                'factors' => $riskData['factors'],
                'metadata' => [
                    'term_id' => $termId,
                    'calculated_by_service' => 'RiskAnalyticsService',
                ],
                'calculated_by' => $calculatedBy,
                'calculated_at' => now(),
            ]
        );

        return $risk;
    }

    /**
     * Calculate and save risk for a group
     */
    public function calculateAndSaveGroupRisk(int $groupId, ?int $termId = null, ?int $calculatedBy = null): Risk
    {
        $termId = $termId ?? $this->getCurrentTermId();
        $riskData = $this->riskCalculator->calculateGroupRisk($groupId, $termId);

        // Calculate risk score based on high risk percentage
        $riskScore = $this->calculateGroupRiskScore($riskData);

        $risk = Risk::updateOrCreate(
            [
                'entity_type' => 'group',
                'entity_id' => $groupId,
                'term_id' => $termId,
            ],
            [
                'risk_level' => $this->mapStatusZoneToRiskLevel($riskData['status_zone']),
                'risk_score' => $riskScore,
                'factors' => [
                    'total_students' => $riskData['total_students'],
                    'risk_distribution' => $riskData['risk_distribution'],
                    'high_risk_percent' => $riskData['high_risk_percent'],
                    'status_zone' => $riskData['status_zone'],
                ],
                'metadata' => [
                    'term_id' => $termId,
                    'group_id' => $groupId,
                    'calculated_by_service' => 'RiskAnalyticsService',
                ],
                'calculated_by' => $calculatedBy,
                'calculated_at' => now(),
            ]
        );

        return $risk;
    }

    /**
     * Calculate risks for all students in a group
     */
    public function calculateRisksForGroup(int $groupId, ?int $termId = null, ?int $calculatedBy = null): Collection
    {
        $termId = $termId ?? $this->getCurrentTermId();

        $students = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('role_in_group', 'student')
            ->pluck('user_id');

        $risks = collect();

        foreach ($students as $studentId) {
            $risks->push($this->calculateAndSaveStudentRisk($studentId, $termId, $calculatedBy));
        }

        return $risks;
    }

    /**
     * Calculate risks for all groups
     */
    public function calculateRisksForAllGroups(?int $termId = null, ?int $calculatedBy = null): Collection
    {
        $groups = Group::all();
        $risks = collect();

        foreach ($groups as $group) {
            $risks->push($this->calculateAndSaveGroupRisk($group->id, $termId, $calculatedBy));
        }

        return $risks;
    }

    /**
     * Get risk report for a term
     */
    public function getRiskReport(?int $termId = null, array $filters = []): array
    {
        $termId = $termId ?? $this->getCurrentTermId();

        $query = Risk::where('term_id', $termId)
            ->active();

        // Apply filters
        if (isset($filters['entity_type'])) {
            $query->byEntityType($filters['entity_type']);
        }

        if (isset($filters['risk_level'])) {
            $query->byLevel($filters['risk_level']);
        }

        if (isset($filters['min_score'])) {
            $query->where('risk_score', '>=', $filters['min_score']);
        }

        $risks = $query->get();

        // Group by risk level
        $byLevel = $risks->groupBy('risk_level')->map(function ($group) {
            return [
                'count' => $group->count(),
                'avg_score' => round($group->avg('risk_score'), 2),
            ];
        });

        // Group by entity type
        $byEntityType = $risks->groupBy('entity_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'high_priority' => $group->whereIn('risk_level', ['high', 'critical'])->count(),
            ];
        });

        // Top risks by score
        $topRisks = $risks->sortByDesc('risk_score')->take(10)->values();

        return [
            'term_id' => $termId,
            'summary' => [
                'total_risks' => $risks->count(),
                'high_priority' => $risks->whereIn('risk_level', ['high', 'critical'])->count(),
                'avg_risk_score' => round($risks->avg('risk_score'), 2),
            ],
            'by_level' => $byLevel,
            'by_entity_type' => $byEntityType,
            'top_risks' => $topRisks->map(function ($risk) {
                return [
                    'id' => $risk->id,
                    'entity_type' => $risk->entity_type,
                    'entity_id' => $risk->entity_id,
                    'risk_level' => $risk->risk_level,
                    'risk_score' => $risk->risk_score,
                    'factors_count' => count($risk->factors ?? []),
                    'calculated_at' => $risk->calculated_at?->toIso8601String(),
                ];
            }),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get risk trends over time
     */
    public function getRiskTrends(?int $termId = null, int $days = 30): array
    {
        $termId = $termId ?? $this->getCurrentTermId();
        $startDate = now()->subDays($days);

        $risks = Risk::where('term_id', $termId)
            ->where('calculated_at', '>=', $startDate)
            ->orderBy('calculated_at')
            ->get();

        // Group by date
        $byDate = $risks->groupBy(function ($risk) {
            return $risk->calculated_at->format('Y-m-d');
        })->map(function ($dayRisks, $date) {
            return [
                'date' => $date,
                'total' => $dayRisks->count(),
                'high_priority' => $dayRisks->whereIn('risk_level', ['high', 'critical'])->count(),
                'avg_score' => round($dayRisks->avg('risk_score'), 2),
            ];
        })->values();

        return [
            'term_id' => $termId,
            'period' => [
                'from' => $startDate->toIso8601String(),
                'to' => now()->toIso8601String(),
            ],
            'trends' => $byDate,
        ];
    }

    /**
     * Get risk distribution by entity
     */
    public function getRiskDistribution(?int $termId = null): array
    {
        $termId = $termId ?? $this->getCurrentTermId();

        $risks = Risk::where('term_id', $termId)
            ->active()
            ->get();

        $distribution = [
            'students' => [
                'total' => 0,
                'by_level' => ['none' => 0, 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0],
            ],
            'groups' => [
                'total' => 0,
                'by_level' => ['none' => 0, 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0],
            ],
        ];

        foreach ($risks as $risk) {
            $entityType = $risk->entity_type;
            $level = $risk->risk_level;

            if (isset($distribution[$entityType . 's'])) {
                $distribution[$entityType . 's']['total']++;
                if (isset($distribution[$entityType . 's']['by_level'][$level])) {
                    $distribution[$entityType . 's']['by_level'][$level]++;
                }
            }
        }

        return [
            'term_id' => $termId,
            'distribution' => $distribution,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Resolve a risk
     */
    public function resolveRisk(int $riskId, string $notes, ?int $resolvedBy = null): Risk
    {
        $risk = Risk::findOrFail($riskId);

        $risk->update([
            'resolved_at' => now(),
            'resolution_notes' => $notes,
            'resolved_by' => $resolvedBy,
        ]);

        return $risk->fresh();
    }

    /**
     * Get risks for a specific entity
     */
    public function getEntityRisks(string $entityType, int $entityId, ?int $termId = null): Collection
    {
        $query = Risk::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->active();

        if ($termId) {
            $query->where('term_id', $termId);
        }

        return $query->orderBy('calculated_at', 'desc')->get();
    }

    /**
     * Calculate risk score from factors
     */
    private function calculateRiskScore(array $factors, string $riskLevel): float
    {
        $baseScore = match ($riskLevel) {
            'critical' => 90,
            'high' => 70,
            'medium' => 50,
            'low' => 30,
            'none' => 0,
            default => 0,
        };

        // Adjust based on factors
        $factorScore = 0;
        foreach ($factors as $factor) {
            $severity = $factor['severity'] ?? 'low';
            $factorScore += match ($severity) {
                'high' => 10,
                'medium' => 5,
                'low' => 2,
                default => 0,
            };
        }

        return min(100, $baseScore + $factorScore);
    }

    /**
     * Calculate group risk score
     */
    private function calculateGroupRiskScore(array $riskData): float
    {
        $highRiskPercent = $riskData['high_risk_percent'] ?? 0;
        $statusZone = $riskData['status_zone'] ?? 'green';

        $baseScore = match ($statusZone) {
            'red' => 80,
            'yellow' => 50,
            'green' => 20,
            default => 0,
        };

        // Add percentage-based adjustment
        $percentScore = min(20, $highRiskPercent * 0.2);

        return min(100, $baseScore + $percentScore);
    }

    /**
     * Map status zone to risk level
     */
    private function mapStatusZoneToRiskLevel(string $statusZone): string
    {
        return match ($statusZone) {
            'red' => 'high',
            'yellow' => 'medium',
            'green' => 'low',
            default => 'low',
        };
    }

    /**
     * Get current term ID
     */
    private function getCurrentTermId(): ?int
    {
        return DB::table('terms')->where('is_current', true)->value('id');
    }
}

