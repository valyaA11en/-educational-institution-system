<?php

namespace App\Services\Analytics;

use App\Models\GradePeriodSummary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RiskCalculator
{
    public function calculateStudentRisk(int $studentId, ?int $termId = null): array
    {
        $termId = $termId ?? $this->getCurrentTermId();

        if (!$termId) {
            return ['risk_level' => 'unknown', 'factors' => []];
        }

        $factors = [];

        // Low grades (avg = 2.0)
        $lowGradesCount = GradePeriodSummary::where('student_user_id', $studentId)
            ->where('term_id', $termId)
            ->where('avg_value', 2.0)
            ->count();
        if ($lowGradesCount > 0) {
            $factors[] = [
                'type' => 'low_grades',
                'severity' => 'high',
                'count' => $lowGradesCount,
            ];
        }

        // Attendance (TODO: implement attendance tracking)
        // $attendanceRate = $this->calculateAttendanceRate($studentId, $termId);
        // if ($attendanceRate < 0.7) {
        //     $factors[] = ['type' => 'low_attendance', 'severity' => 'medium', 'rate' => $attendanceRate];
        // }

        // Overdue assignments (TODO: implement)
        // $overdueCount = $this->getOverdueAssignmentsCount($studentId);
        // if ($overdueCount > 0) {
        //     $factors[] = ['type' => 'overdue_assignments', 'severity' => 'medium', 'count' => $overdueCount];
        // }

        $riskLevel = $this->determineRiskLevel($factors);

        return [
            'risk_level' => $riskLevel,
            'factors' => $factors,
            'term_id' => $termId,
        ];
    }

    public function calculateGroupRisk(int $groupId, ?int $termId = null): array
    {
        $termId = $termId ?? $this->getCurrentTermId();

        $students = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('role_in_group', 'student')
            ->pluck('user_id');

        $riskCounts = ['low' => 0, 'medium' => 0, 'high' => 0];
        $totalStudents = $students->count();

        foreach ($students as $studentId) {
            $risk = $this->calculateStudentRisk($studentId, $termId);
            $riskCounts[$risk['risk_level']]++;
        }

        $highRiskPercent = $totalStudents > 0 ? ($riskCounts['high'] / $totalStudents) * 100 : 0;

        return [
            'group_id' => $groupId,
            'total_students' => $totalStudents,
            'risk_distribution' => $riskCounts,
            'high_risk_percent' => round($highRiskPercent, 2),
            'status_zone' => $this->getStatusZone($highRiskPercent),
        ];
    }

    private function determineRiskLevel(array $factors): string
    {
        $highSeverityCount = collect($factors)->where('severity', 'high')->count();
        $mediumSeverityCount = collect($factors)->where('severity', 'medium')->count();

        if ($highSeverityCount > 0) {
            return 'high';
        }

        if ($mediumSeverityCount >= 2) {
            return 'medium';
        }

        if ($mediumSeverityCount > 0 || count($factors) > 0) {
            return 'low';
        }

        return 'none';
    }

    private function getStatusZone(float $highRiskPercent): string
    {
        if ($highRiskPercent >= 30) {
            return 'red';
        }
        if ($highRiskPercent >= 15) {
            return 'yellow';
        }
        return 'green';
    }

    private function getCurrentTermId(): ?int
    {
        return DB::table('terms')->where('is_current', true)->value('id');
    }
}

