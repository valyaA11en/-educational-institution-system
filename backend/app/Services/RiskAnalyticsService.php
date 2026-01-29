<?php

namespace App\Services;

use App\Models\Risk;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RiskAnalyticsService
{
    /**
     * Analyze risks for a specific entity
     */
    public function analyzeEntity(string $entityType, int $entityId, ?int $tenantId = null): array
    {
        $query = Risk::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->active();

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        $risks = $query->get();

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'total_risks' => $risks->count(),
            'critical_risks' => $risks->where('risk_level', 'critical')->count(),
            'high_risks' => $risks->where('risk_level', 'high')->count(),
            'medium_risks' => $risks->where('risk_level', 'medium')->count(),
            'low_risks' => $risks->where('risk_level', 'low')->count(),
            'average_score' => $risks->avg('risk_score') ?? 0,
            'risks' => $risks,
        ];
    }

    /**
     * Get risk trends over time
     */
    public function getRiskTrends(?int $tenantId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Risk::query();

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        if ($startDate) {
            $query->where('calculated_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('calculated_at', '<=', $endDate);
        }

        $risks = $query->orderBy('calculated_at')->get();

        // Group by date
        $trends = [];
        foreach ($risks as $risk) {
            $date = $risk->calculated_at ? $risk->calculated_at->format('Y-m-d') : Carbon::now()->format('Y-m-d');
            if (!isset($trends[$date])) {
                $trends[$date] = [
                    'date' => $date,
                    'total' => 0,
                    'critical' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                ];
            }
            $trends[$date]['total']++;
            $level = $risk->risk_level ?? 'low';
            if (isset($trends[$date][$level])) {
                $trends[$date][$level]++;
            }
        }

        return array_values($trends);
    }

    /**
     * Get risk distribution by entity type
     */
    public function getRiskDistribution(?int $tenantId = null): array
    {
        $query = Risk::active();
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        $risks = $query->get();

        $distribution = [];
        foreach ($risks as $risk) {
            $type = $risk->entity_type;
            if (!isset($distribution[$type])) {
                $distribution[$type] = [
                    'type' => $type,
                    'count' => 0,
                    'critical' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                ];
            }
            $distribution[$type]['count']++;
            $level = $risk->risk_level ?? 'low';
            if (isset($distribution[$type][$level])) {
                $distribution[$type][$level]++;
            }
        }

        return array_values($distribution);
    }

    /**
     * Generate comprehensive risk report
     */
    public function generateReport(?int $tenantId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Risk::query();

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        if ($startDate) {
            $query->where('calculated_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('calculated_at', '<=', $endDate);
        }

        $risks = $query->get();

        return [
            'summary' => [
                'total' => $risks->count(),
                'active' => $risks->whereNull('resolved_at')->count(),
                'resolved' => $risks->whereNotNull('resolved_at')->count(),
                'critical' => $risks->where('risk_level', 'critical')->count(),
                'high' => $risks->where('risk_level', 'high')->count(),
                'medium' => $risks->where('risk_level', 'medium')->count(),
                'low' => $risks->where('risk_level', 'low')->count(),
            ],
            'by_type' => $this->getRiskDistribution($tenantId),
            'trends' => $this->getRiskTrends($tenantId, $startDate, $endDate),
            'top_risks' => $risks->sortByDesc('risk_score')->take(10)->values(),
        ];
    }

    /**
     * Detect and create risks for a student
     */
    public function detectStudentRisks(int $studentId, ?int $tenantId = null): array
    {
        $student = User::find($studentId);
        if (!$student) {
            return [];
        }

        $tenantId = $tenantId ?? $student->tenant_id;
        $risks = [];
        $factors = [];
        $totalScore = 0;

        // 1. Check attendance rate
        $attendanceRate = $this->calculateAttendanceRate($studentId);
        if ($attendanceRate < 0.7) {
            $score = (0.7 - $attendanceRate) * 100; // 0-30 points
            $level = $this->calculateRiskLevel($score);
            $factors[] = [
                'type' => 'low_attendance',
                'value' => $attendanceRate,
                'threshold' => 0.7,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // 2. Check average grade
        $averageGrade = $this->calculateAverageGrade($studentId);
        if ($averageGrade < 3.0) {
            $score = (3.0 - $averageGrade) * 20; // 0-20 points
            $level = $this->calculateRiskLevel($score);
            $factors[] = [
                'type' => 'low_average_grade',
                'value' => $averageGrade,
                'threshold' => 3.0,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // 3. Check failing grades count (2 or less)
        $failingGrades = $this->countFailingGrades($studentId);
        if ($failingGrades > 0) {
            $score = min($failingGrades * 15, 30); // 15 points per failing grade, max 30
            $level = $this->calculateRiskLevel($score);
            $factors[] = [
                'type' => 'failing_grades',
                'value' => $failingGrades,
                'threshold' => 0,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // 4. Check recent activity (no grades in last 30 days)
        $recentActivity = $this->hasRecentActivity($studentId, 30);
        if (!$recentActivity) {
            $score = 20;
            $level = $this->calculateRiskLevel($score);
            $factors[] = [
                'type' => 'no_recent_activity',
                'value' => 0,
                'threshold' => 1,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // 5. Check assignment submissions (low submission rate)
        $submissionRate = $this->calculateSubmissionRate($studentId);
        if ($submissionRate < 0.5) {
            $score = (0.5 - $submissionRate) * 20; // 0-10 points
            $level = $this->calculateRiskLevel($score);
            $factors[] = [
                'type' => 'low_submission_rate',
                'value' => $submissionRate,
                'threshold' => 0.5,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // Create risk if any factors found
        if (!empty($factors)) {
            $riskLevel = $this->calculateRiskLevel($totalScore);
            
            // Check if risk already exists
            $existingRisk = Risk::where('entity_type', 'user')
                ->where('entity_id', $studentId)
                ->where('tenant_id', $tenantId)
                ->whereNull('resolved_at')
                ->first();

            if ($existingRisk) {
                // Update existing risk
                $existingRisk->risk_level = $riskLevel;
                $existingRisk->risk_score = min($totalScore, 100);
                $existingRisk->factors = $factors;
                $existingRisk->calculated_at = now();
                $existingRisk->calculated_by = auth()->id();
                $existingRisk->save();
                $risks[] = $existingRisk;
            } else {
                // Create new risk
                $risk = Risk::create([
                    'entity_type' => 'user',
                    'entity_id' => $studentId,
                    'risk_level' => $riskLevel,
                    'risk_score' => min($totalScore, 100),
                    'factors' => $factors,
                    'metadata' => [
                        'student_name' => $student->fio,
                        'attendance_rate' => $attendanceRate,
                        'average_grade' => $averageGrade,
                        'failing_grades' => $failingGrades,
                    ],
                    'tenant_id' => $tenantId,
                    'calculated_by' => auth()->id(),
                    'calculated_at' => now(),
                ]);
                $risks[] = $risk;
            }
        }

        return $risks;
    }

    /**
     * Detect and create risks for a group
     */
    public function detectGroupRisks(int $groupId, ?int $tenantId = null): array
    {
        $group = Group::find($groupId);
        if (!$group) {
            return [];
        }

        $tenantId = $tenantId ?? $group->tenant_id;
        $risks = [];
        $factors = [];
        $totalScore = 0;

        // Get all students in the group
        $studentIds = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('role_in_group', 'student')
            ->pluck('user_id')
            ->toArray();

        if (empty($studentIds)) {
            return [];
        }

        // 1. Check group average grade
        $groupAverageGrade = $this->calculateGroupAverageGrade($studentIds);
        if ($groupAverageGrade < 3.5) {
            $score = (3.5 - $groupAverageGrade) * 20; // 0-20 points
            $factors[] = [
                'type' => 'low_group_average',
                'value' => $groupAverageGrade,
                'threshold' => 3.5,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // 2. Check percentage of students with low attendance
        $lowAttendanceCount = 0;
        foreach ($studentIds as $studentId) {
            $attendanceRate = $this->calculateAttendanceRate($studentId);
            if ($attendanceRate < 0.7) {
                $lowAttendanceCount++;
            }
        }
        $lowAttendancePercentage = $lowAttendanceCount / count($studentIds);
        if ($lowAttendancePercentage > 0.3) {
            $score = ($lowAttendancePercentage - 0.3) * 50; // 0-35 points
            $factors[] = [
                'type' => 'high_low_attendance_percentage',
                'value' => $lowAttendancePercentage,
                'threshold' => 0.3,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // 3. Check percentage of students with failing grades
        $failingStudentsCount = 0;
        foreach ($studentIds as $studentId) {
            $failingGrades = $this->countFailingGrades($studentId);
            if ($failingGrades > 0) {
                $failingStudentsCount++;
            }
        }
        $failingPercentage = $failingStudentsCount / count($studentIds);
        if ($failingPercentage > 0.2) {
            $score = ($failingPercentage - 0.2) * 50; // 0-40 points
            $factors[] = [
                'type' => 'high_failing_students_percentage',
                'value' => $failingPercentage,
                'threshold' => 0.2,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // 4. Check group activity (recent lessons/grades)
        $recentActivity = $this->hasGroupRecentActivity($groupId, 30);
        if (!$recentActivity) {
            $score = 15;
            $factors[] = [
                'type' => 'no_recent_group_activity',
                'value' => 0,
                'threshold' => 1,
                'score' => $score,
            ];
            $totalScore += $score;
        }

        // Create risk if any factors found
        if (!empty($factors)) {
            $riskLevel = $this->calculateRiskLevel($totalScore);
            
            // Check if risk already exists
            $existingRisk = Risk::where('entity_type', 'group')
                ->where('entity_id', $groupId)
                ->where('tenant_id', $tenantId)
                ->whereNull('resolved_at')
                ->first();

            if ($existingRisk) {
                // Update existing risk
                $existingRisk->risk_level = $riskLevel;
                $existingRisk->risk_score = min($totalScore, 100);
                $existingRisk->factors = $factors;
                $existingRisk->calculated_at = now();
                $existingRisk->calculated_by = auth()->id();
                $existingRisk->save();
                $risks[] = $existingRisk;
            } else {
                // Create new risk
                $risk = Risk::create([
                    'entity_type' => 'group',
                    'entity_id' => $groupId,
                    'risk_level' => $riskLevel,
                    'risk_score' => min($totalScore, 100),
                    'factors' => $factors,
                    'metadata' => [
                        'group_name' => $group->name,
                        'student_count' => count($studentIds),
                        'group_average_grade' => $groupAverageGrade,
                        'low_attendance_percentage' => $lowAttendancePercentage,
                        'failing_students_percentage' => $failingPercentage,
                    ],
                    'tenant_id' => $tenantId,
                    'calculated_by' => auth()->id(),
                    'calculated_at' => now(),
                ]);
                $risks[] = $risk;
            }
        }

        return $risks;
    }

    /**
     * Resolve a risk
     */
    public function resolveRisk(int $riskId, int $resolvedBy, ?string $resolutionNotes = null): Risk
    {
        $risk = Risk::findOrFail($riskId);
        $risk->resolved_at = now();
        $risk->resolved_by = $resolvedBy;
        
        if ($resolutionNotes) {
            $risk->resolution_notes = $resolutionNotes;
        }
        
        $risk->save();
        
        return $risk;
    }

    /**
     * Calculate attendance rate for a student
     */
    private function calculateAttendanceRate(int $studentId): float
    {
        $totalLessons = DB::table('attendance')
            ->join('lessons', 'attendance.lesson_id', '=', 'lessons.id')
            ->where('attendance.student_user_id', $studentId)
            ->where('lessons.date', '>=', Carbon::now()->subMonths(3))
            ->count();

        if ($totalLessons === 0) {
            return 1.0; // No lessons = 100% attendance
        }

        $presentLessons = DB::table('attendance')
            ->join('lessons', 'attendance.lesson_id', '=', 'lessons.id')
            ->where('attendance.student_user_id', $studentId)
            ->where('attendance.status', 'present')
            ->where('lessons.date', '>=', Carbon::now()->subMonths(3))
            ->count();

        return $totalLessons > 0 ? $presentLessons / $totalLessons : 1.0;
    }

    /**
     * Calculate average grade for a student
     */
    private function calculateAverageGrade(int $studentId): float
    {
        $grades = DB::table('grades')
            ->where('student_user_id', $studentId)
            ->where('created_at', '>=', Carbon::now()->subMonths(3))
            ->select('value', 'weight')
            ->get();

        if ($grades->isEmpty()) {
            return 5.0; // No grades = perfect score
        }

        $totalWeighted = 0;
        $totalWeight = 0;

        foreach ($grades as $grade) {
            $weight = $grade->weight ?? 1;
            $totalWeighted += $grade->value * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $totalWeighted / $totalWeight : 5.0;
    }

    /**
     * Count failing grades (2 or less) for a student
     */
    private function countFailingGrades(int $studentId): int
    {
        return DB::table('grades')
            ->where('student_user_id', $studentId)
            ->where('value', '<=', 2)
            ->where('created_at', '>=', Carbon::now()->subMonths(3))
            ->count();
    }

    /**
     * Check if student has recent activity (grades in last N days)
     */
    private function hasRecentActivity(int $studentId, int $days = 30): bool
    {
        return DB::table('grades')
            ->where('student_user_id', $studentId)
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->exists();
    }

    /**
     * Calculate assignment submission rate
     */
    private function calculateSubmissionRate(int $studentId): float
    {
        // Get assignments targeted to student (directly or through group)
        $totalAssignments = DB::table('assignment_targets')
            ->where(function ($query) use ($studentId) {
                $query->where('student_user_id', $studentId)
                      ->orWhereIn('group_id', function ($subQuery) use ($studentId) {
                          $subQuery->select('group_id')
                                   ->from('group_members')
                                   ->where('user_id', $studentId)
                                   ->where('role_in_group', 'student');
                      });
            })
            ->count();

        if ($totalAssignments === 0) {
            return 1.0; // No assignments = 100% submission
        }

        // Get submitted assignments
        $submittedAssignments = DB::table('submissions')
            ->where('student_user_id', $studentId)
            ->whereIn('status', ['submitted', 'graded'])
            ->where('created_at', '>=', Carbon::now()->subMonths(3))
            ->count();

        return $totalAssignments > 0 ? $submittedAssignments / $totalAssignments : 1.0;
    }

    /**
     * Calculate group average grade
     */
    private function calculateGroupAverageGrade(array $studentIds): float
    {
        if (empty($studentIds)) {
            return 5.0;
        }

        $grades = DB::table('grades')
            ->whereIn('student_user_id', $studentIds)
            ->where('created_at', '>=', Carbon::now()->subMonths(3))
            ->select('value', 'weight')
            ->get();

        if ($grades->isEmpty()) {
            return 5.0;
        }

        $totalWeighted = 0;
        $totalWeight = 0;

        foreach ($grades as $grade) {
            $weight = $grade->weight ?? 1;
            $totalWeighted += $grade->value * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $totalWeighted / $totalWeight : 5.0;
    }

    /**
     * Check if group has recent activity
     */
    private function hasGroupRecentActivity(int $groupId, int $days = 30): bool
    {
        // Check if there are recent lessons for this group
        return DB::table('lessons')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->where('schedule_items.group_id', $groupId)
            ->where('lessons.date', '>=', Carbon::now()->subDays($days))
            ->exists();
    }

    /**
     * Calculate risk level based on score
     */
    private function calculateRiskLevel(float $score): string
    {
        if ($score >= 70) {
            return 'critical';
        } elseif ($score >= 50) {
            return 'high';
        } elseif ($score >= 30) {
            return 'medium';
        } elseif ($score >= 10) {
            return 'low';
        } else {
            return 'low';
        }
    }
}
