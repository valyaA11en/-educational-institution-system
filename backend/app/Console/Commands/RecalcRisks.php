<?php

namespace App\Console\Commands;

use App\Models\Risk;
use App\Models\User;
use App\Models\Notification;
use App\Services\Outbox\OutboxService;
use App\Support\Events\EventTypes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalcRisks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:recalc-risks {--term-id= : Specific term ID to recalculate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate risks for all students';

    /**
     * Execute the console command.
     */
    public function handle(OutboxService $outboxService): void
    {
        $this->info('Starting risk recalculation...');
        Log::info('Starting risk recalculation');

        $termId = $this->option('term-id') 
            ? (int) $this->option('term-id')
            : $this->getCurrentTermId();

        if (!$termId) {
            $this->error('No current term found. Please specify --term-id');
            Log::error('No current term found for risk recalculation');
            return;
        }

        $this->info("Processing term ID: {$termId}");

        // Get all students
        $students = User::whereHas('roles', function ($query) {
            $query->where('name', 'student');
        })->get();

        $this->info("Found {$students->count()} students");

        $processed = 0;
        $risksCreated = 0;
        $notificationsSent = 0;

        foreach ($students as $student) {
            try {
                $risks = $this->calculateRisksForStudent($student->id, $termId);
                
                foreach ($risks as $riskData) {
                    $risk = Risk::updateOrCreate(
                        [
                            'user_id' => $student->id,
                            'term_id' => $termId,
                            'risk_type' => $riskData['risk_type'],
                        ],
                        [
                            'level' => $riskData['level'],
                            'score' => $riskData['score'],
                            'details_json' => $riskData['details'],
                            'calculated_at' => now(),
                        ]
                    );

                    $risksCreated++;

                    // Send notification if red level
                    if ($riskData['level'] === 'red') {
                        $this->sendRiskNotification($student->id, $riskData, $outboxService);
                        $notificationsSent++;
                    }

                    // Create outbox event
                    $outboxService->record(
                        EventTypes::RISKS_UPDATED,
                        null, // System event
                        'risk',
                        $risk->id,
                        [
                            'user_id' => $student->id,
                            'term_id' => $termId,
                            'risk_type' => $riskData['risk_type'],
                            'level' => $riskData['level'],
                            'score' => $riskData['score'],
                        ]
                    );
                }

                $processed++;
            } catch (\Exception $e) {
                $this->error("Failed to process student {$student->id}: {$e->getMessage()}");
                Log::error("Failed to process student {$student->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Processed {$processed} students");
        $this->info("Created/updated {$risksCreated} risks");
        $this->info("Sent {$notificationsSent} notifications");
        Log::info("Risk recalculation completed", [
            'processed' => $processed,
            'risks_created' => $risksCreated,
            'notifications_sent' => $notificationsSent,
        ]);
    }

    /**
     * Calculate risks for a student
     */
    private function calculateRisksForStudent(int $userId, int $termId): array
    {
        $risks = [];

        // 1. avg_low: если term avg <=2.0 => red, <=2.5 => yellow
        $avgRisk = $this->calculateAvgRisk($userId, $termId);
        if ($avgRisk) {
            $risks[] = $avgRisk;
        }

        // 2. absences_high: если за 30 дней absent >=5 => yellow, >=10 => red
        $absenceRisk = $this->calculateAbsenceRisk($userId);
        if ($absenceRisk) {
            $risks[] = $absenceRisk;
        }

        // 3. debts_high: если просроченных submissions (status!=submitted/graded) > N => yellow/red
        $debtRisk = $this->calculateDebtRisk($userId);
        if ($debtRisk) {
            $risks[] = $debtRisk;
        }

        // 4. no_activity: если нет submissions/material_reads за 14 дней => yellow
        $activityRisk = $this->calculateActivityRisk($userId);
        if ($activityRisk) {
            $risks[] = $activityRisk;
        }

        return $risks;
    }

    /**
     * Calculate average grade risk
     */
    private function calculateAvgRisk(int $userId, int $termId): ?array
    {
        // Get average grade for term
        $avg = DB::table('grade_period_summaries')
            ->where('student_user_id', $userId)
            ->where('term_id', $termId)
            ->avg('avg_value');

        if ($avg === null) {
            return null;
        }

        $level = 'green';
        $score = 0;

        if ($avg <= 2.0) {
            $level = 'red';
            $score = 90;
        } elseif ($avg <= 2.5) {
            $level = 'yellow';
            $score = 50;
        }

        if ($level === 'green') {
            return null; // No risk
        }

        return [
            'risk_type' => 'avg_low',
            'level' => $level,
            'score' => $score,
            'details' => [
                'avg_value' => (float) $avg,
                'threshold_red' => 2.0,
                'threshold_yellow' => 2.5,
            ],
        ];
    }

    /**
     * Calculate absence risk
     */
    private function calculateAbsenceRisk(int $userId): ?array
    {
        $daysAgo = now()->subDays(30);

        $absentCount = DB::table('attendance')
            ->where('student_user_id', $userId)
            ->where('status', 'absent')
            ->where('created_at', '>=', $daysAgo)
            ->count();

        $level = 'green';
        $score = 0;

        if ($absentCount >= 10) {
            $level = 'red';
            $score = 90;
        } elseif ($absentCount >= 5) {
            $level = 'yellow';
            $score = 50;
        }

        if ($level === 'green') {
            return null; // No risk
        }

        return [
            'risk_type' => 'absences_high',
            'level' => $level,
            'score' => $score,
            'details' => [
                'absent_count' => $absentCount,
                'period_days' => 30,
                'threshold_yellow' => 5,
                'threshold_red' => 10,
            ],
        ];
    }

    /**
     * Calculate debt risk (overdue submissions)
     */
    private function calculateDebtRisk(int $userId): ?array
    {
        // Count submissions that are not submitted or graded
        // and are past due date
        $overdueCount = DB::table('submissions')
            ->join('assignments', 'submissions.assignment_id', '=', 'assignments.id')
            ->where('submissions.student_user_id', $userId)
            ->whereNotIn('submissions.status', ['submitted', 'graded'])
            ->whereNotNull('assignments.due_at')
            ->where('assignments.due_at', '<', now())
            ->count();

        $level = 'green';
        $score = 0;

        // Thresholds: > 5 => red, > 2 => yellow
        if ($overdueCount > 5) {
            $level = 'red';
            $score = 90;
        } elseif ($overdueCount > 2) {
            $level = 'yellow';
            $score = 50;
        }

        if ($level === 'green') {
            return null; // No risk
        }

        return [
            'risk_type' => 'debts_high',
            'level' => $level,
            'score' => $score,
            'details' => [
                'overdue_count' => $overdueCount,
                'threshold_yellow' => 2,
                'threshold_red' => 5,
            ],
        ];
    }

    /**
     * Calculate activity risk
     */
    private function calculateActivityRisk(int $userId): ?array
    {
        $daysAgo = now()->subDays(14);

        // Check for submissions in last 14 days
        $hasSubmissions = DB::table('submissions')
            ->where('student_user_id', $userId)
            ->where('created_at', '>=', $daysAgo)
            ->exists();

        // Check for material reads (if material_reads table exists)
        $hasMaterialReads = false;
        if (DB::getSchemaBuilder()->hasTable('material_reads')) {
            $hasMaterialReads = DB::table('material_reads')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $daysAgo)
                ->exists();
        }

        if ($hasSubmissions || $hasMaterialReads) {
            return null; // Has activity, no risk
        }

        return [
            'risk_type' => 'no_activity',
            'level' => 'yellow',
            'score' => 50,
            'details' => [
                'period_days' => 14,
                'has_submissions' => false,
                'has_material_reads' => false,
            ],
        ];
    }

    /**
     * Send notification for red risk
     */
    private function sendRiskNotification(int $userId, array $riskData, OutboxService $outboxService): void
    {
        // Create notification for student
        Notification::create([
            'user_id' => $userId,
            'type' => 'risk.high',
            'payload_json' => [
                'risk_type' => $riskData['risk_type'],
                'level' => $riskData['level'],
                'score' => $riskData['score'],
                'details' => $riskData['details'],
            ],
            'channel' => 'in_app',
            'status' => 'new',
        ]);

        // Also notify parents if linked
        $parents = DB::table('user_link_parent_child')
            ->where('child_user_id', $userId)
            ->pluck('parent_user_id');

        foreach ($parents as $parentId) {
            Notification::create([
                'user_id' => $parentId,
                'type' => 'risk.high.child',
                'payload_json' => [
                    'student_id' => $userId,
                    'risk_type' => $riskData['risk_type'],
                    'level' => $riskData['level'],
                    'score' => $riskData['score'],
                    'details' => $riskData['details'],
                ],
                'channel' => 'in_app',
                'status' => 'new',
            ]);
        }

        // Notify curator if student is in a group
        $groupIds = DB::table('group_members')
            ->where('user_id', $userId)
            ->where('role_in_group', 'student')
            ->pluck('group_id');

        foreach ($groupIds as $groupId) {
            $curatorId = DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('role_in_group', 'curator')
                ->value('user_id');

            if ($curatorId) {
                Notification::create([
                    'user_id' => $curatorId,
                    'type' => 'risk.high.student',
                    'payload_json' => [
                        'student_id' => $userId,
                        'group_id' => $groupId,
                        'risk_type' => $riskData['risk_type'],
                        'level' => $riskData['level'],
                        'score' => $riskData['score'],
                        'details' => $riskData['details'],
                    ],
                    'channel' => 'in_app',
                    'status' => 'new',
                ]);
            }
        }
    }

    /**
     * Get current term ID
     */
    private function getCurrentTermId(): ?int
    {
        return DB::table('terms')->where('is_current', true)->value('id');
    }
}


