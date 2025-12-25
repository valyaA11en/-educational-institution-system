<?php

namespace App\Console\Commands;

use App\Models\GradePeriodSummary;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserLinkParentChild;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckLowGradesNotifications extends Command
{
    protected $signature = 'notifications:check-low-grades';

    protected $description = 'Check and send notifications about low average grades (2.0)';

    public function handle(): int
    {
        // Check if it's the last day of month
        $today = now();
        $lastDayOfMonth = $today->copy()->endOfMonth();

        if (!$today->isSameDay($lastDayOfMonth)) {
            $this->info('Not the last day of month. Skipping.');
            return Command::SUCCESS;
        }

        $this->info('Checking low grades for notifications...');

        // Get threshold from settings (default: 0.1 = 10%)
        $settingsService = app(\App\Services\SettingsService::class);
        $thresholdData = $settingsService->get('journal.monthly.avg2.threshold_percent', ['value' => 10]);
        $thresholdPercent = isset($thresholdData['value']) ? (float) $thresholdData['value'] / 100 : 0.1;

        // Get current term
        $currentTerm = DB::table('terms')
            ->where('is_current', true)
            ->first();

        if (!$currentTerm) {
            $this->error('No current term found');
            return Command::FAILURE;
        }

        // Find students with average = 2.0
        $lowGradeStudents = GradePeriodSummary::where('term_id', $currentTerm->id)
            ->where('avg_value', 2.0)
            ->with(['student', 'subject'])
            ->get()
            ->groupBy('student_user_id');

        $notificationsCreated = 0;

        foreach ($lowGradeStudents as $studentId => $summaries) {
            $student = User::find($studentId);
            if (!$student) {
                continue;
            }

            // Notify student
            $subjects = $summaries->pluck('subject.name')->join(', ');
            Notification::create([
                'user_id' => $studentId,
                'type' => 'grade.low_average',
                'payload_json' => [
                    'term_id' => $currentTerm->id,
                    'term_name' => $currentTerm->name,
                    'subjects' => $summaries->pluck('subject.name')->toArray(),
                    'message' => "У вас средняя оценка 2.0 по предметам: {$subjects}",
                ],
                'channel' => 'in_app',
                'status' => 'new',
            ]);
            $notificationsCreated++;

            // Notify parents
            $parentLinks = UserLinkParentChild::where('student_user_id', $studentId)
                ->where('status', 'approved')
                ->with('parent')
                ->get();

            foreach ($parentLinks as $link) {
                $parent = $link->parentUser;
                if (!$parent) {
                    continue;
                }

                Notification::create([
                    'user_id' => $link->parent_user_id,
                    'type' => 'grade.low_average_child',
                    'payload_json' => [
                        'student_id' => $studentId,
                        'student_fio' => $student->fio,
                        'term_id' => $currentTerm->id,
                        'term_name' => $currentTerm->name,
                        'subjects' => $summaries->pluck('subject.name')->toArray(),
                        'message' => "У вашего ребенка {$student->fio} средняя оценка 2.0 по предметам: {$subjects}",
                    ],
                    'channel' => 'in_app',
                    'status' => 'new',
                ]);
                $notificationsCreated++;
            }
        }

        // Notify curators about groups with high % of low grades
        $groups = Group::with('members')->get();

        foreach ($groups as $group) {
            $groupMembers = $group->members()
                ->wherePivot('role_in_group', 'student')
                ->pluck('users.id');

            if ($groupMembers->isEmpty()) {
                continue;
            }

            $groupLowGradeCount = GradePeriodSummary::where('term_id', $currentTerm->id)
                ->where('avg_value', 2.0)
                ->whereIn('student_user_id', $groupMembers)
                ->distinct('student_user_id')
                ->count('student_user_id');

            $totalStudents = $groupMembers->count();
            $percent = $totalStudents > 0 ? $groupLowGradeCount / $totalStudents : 0;

            if ($percent >= $thresholdPercent) {
                $lowGradeStudentIds = GradePeriodSummary::where('term_id', $currentTerm->id)
                    ->where('avg_value', 2.0)
                    ->whereIn('student_user_id', $groupMembers)
                    ->distinct('student_user_id')
                    ->pluck('student_user_id');

                $lowGradeStudents = User::whereIn('id', $lowGradeStudentIds)->pluck('fio')->toArray();

                // Find curators for this group
                $curators = $group->members()
                    ->wherePivot('role_in_group', 'curator')
                    ->get();

                if ($curators->isEmpty()) {
                    continue;
                }

                foreach ($curators as $curator) {
                    Notification::create([
                        'user_id' => $curator->id,
                        'type' => 'grade.low_average_group',
                        'payload_json' => [
                            'group_id' => $group->id,
                            'group_name' => $group->name,
                            'term_id' => $currentTerm->id,
                            'term_name' => $currentTerm->name,
                            'percent' => round($percent * 100, 1),
                            'low_grade_count' => $groupLowGradeCount,
                            'total_students' => $totalStudents,
                            'students' => $lowGradeStudents,
                            'message' => "В группе {$group->name} {$groupLowGradeCount} студентов (" . round($percent * 100, 1) . "%) имеют среднюю оценку 2.0",
                        ],
                        'channel' => 'in_app',
                        'status' => 'new',
                    ]);
                    $notificationsCreated++;
                }
            }
        }

        $this->info("Created {$notificationsCreated} notifications");

        return Command::SUCCESS;
    }
}

