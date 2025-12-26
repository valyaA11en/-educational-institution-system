<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\User;
use App\Services\NotificationService;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Support\Events\EventTypes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExamReminderCommand extends Command
{
    protected $signature = 'exams:remind';
    protected $description = 'Send exam reminders 48h and 6h before exam';

    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $now = now();

        // Exams in 48 hours (±6 hours window)
        $exams48h = Exam::whereBetween('date_at', [
            $now->copy()->addHours(42),
            $now->copy()->addHours(54),
        ])->get();

        // Exams in 6 hours (±2 hours window)
        $exams6h = Exam::whereBetween('date_at', [
            $now->copy()->addHours(4),
            $now->copy()->addHours(8),
        ])->get();

        foreach ($exams48h as $exam) {
            $this->sendReminder($exam, 48);
        }

        foreach ($exams6h as $exam) {
            $this->sendReminder($exam, 6);
        }

        $this->info("Processed " . ($exams48h->count() + $exams6h->count()) . " exams");
    }

    private function sendReminder(Exam $exam, int $hours): void
    {
        // Notify students in group
        if ($exam->group_id) {
            $studentIds = DB::table('group_members')
                ->where('group_id', $exam->group_id)
                ->pluck('user_id');
            
            $students = User::whereIn('id', $studentIds)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'student');
                })
                ->get();

            foreach ($students as $student) {
                $this->notificationService->create(
                    $student->id,
                    'exam.reminder',
                    [
                        'title' => "Напоминание об экзамене",
                        'body' => "Экзамен '{$exam->title}' через {$hours} часов",
                        'exam_id' => $exam->id,
                        'url' => "/exams/{$exam->id}",
                    ],
                    'in_app'
                );
            }
        }

        // Notify commission
        foreach ($exam->commissions as $commission) {
            $this->notificationService->create(
                $commission->user_id,
                'exam.reminder',
                [
                    'title' => "Напоминание об экзамене",
                    'body' => "Экзамен '{$exam->title}' через {$hours} часов",
                    'exam_id' => $exam->id,
                    'url' => "/exams/{$exam->id}",
                ],
                'in_app'
            );
        }

        // Trigger webhook
        WebhookController::trigger(EventTypes::EXAM_REMINDER, [
            'exam_id' => $exam->id,
            'title' => $exam->title,
            'hours_before' => $hours,
            'date_at' => $exam->date_at,
        ]);
    }
}

