<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckTicketSla extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue tickets and mark them, send notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for overdue tickets...');

        // Find tickets that are open/in_progress where now > resolution_due_at and is_overdue=false
        $overdueTickets = Ticket::whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('resolution_due_at')
            ->where('resolution_due_at', '<', now())
            ->where('is_overdue', false)
            ->get();

        $count = 0;

        foreach ($overdueTickets as $ticket) {
            DB::beginTransaction();
            try {
                // Mark as overdue
                $ticket->is_overdue = true;
                $ticket->save();

                // Create notifications
                $this->sendOverdueNotifications($ticket);

                DB::commit();
                $count++;

                $this->info("Marked ticket #{$ticket->id} as overdue");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to process overdue ticket #{$ticket->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("Failed to process ticket #{$ticket->id}: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$count} overdue ticket(s)");

        return Command::SUCCESS;
    }

    /**
     * Send notifications for overdue ticket
     */
    private function sendOverdueNotifications(Ticket $ticket): void
    {
        $overdueMinutes = now()->diffInMinutes($ticket->resolution_due_at);
        $overdueHours = round($overdueMinutes / 60, 2);

        // Notify assigned_to (if exists)
        if ($ticket->assigned_to) {
            Notification::create([
                'user_id' => $ticket->assigned_to,
                'type' => 'ticket.overdue',
                'payload_json' => [
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'overdue_minutes' => $overdueMinutes,
                    'overdue_hours' => $overdueHours,
                    'resolution_due_at' => $ticket->resolution_due_at?->toIso8601String(),
                ],
                'channel' => 'in_app',
                'status' => 'new',
            ]);
        }

        // Notify admin users
        $adminUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        foreach ($adminUsers as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'ticket.overdue',
                'payload_json' => [
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'overdue_minutes' => $overdueMinutes,
                    'overdue_hours' => $overdueHours,
                    'resolution_due_at' => $ticket->resolution_due_at?->toIso8601String(),
                    'assigned_to' => $ticket->assigned_to,
                ],
                'channel' => 'in_app',
                'status' => 'new',
            ]);
        }

        // Notify creator (optional - can be disabled if needed)
        if ($ticket->created_by && $ticket->created_by !== $ticket->assigned_to) {
            Notification::create([
                'user_id' => $ticket->created_by,
                'type' => 'ticket.overdue',
                'payload_json' => [
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'overdue_minutes' => $overdueMinutes,
                    'overdue_hours' => $overdueHours,
                    'message' => 'Ваш тикет просрочен',
                ],
                'channel' => 'in_app',
                'status' => 'new',
            ]);
        }
    }
}
