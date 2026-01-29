<?php

namespace App\Services\Ticket;

use App\Models\Ticket;
use App\Models\TicketSLA;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SlaService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Apply SLA to a ticket based on category and priority
     */
    public function applySlaToTicket(Ticket $ticket): void
    {
        $slaConfig = TicketSLA::getForTicket($ticket->category, $ticket->priority);

        if (!$slaConfig) {
            // Fallback to default calculation
            $ticket->sla_due_at = $this->calculateDueDate($ticket->priority, $ticket->created_at);
            return;
        }

        $createdAt = $ticket->created_at ?? now();

        $ticket->sla_level = $slaConfig->sla_level;
        $ticket->response_time_minutes = $slaConfig->response_time_minutes;
        $ticket->resolution_time_minutes = $slaConfig->resolution_time_minutes;
        $ticket->sla_response_due_at = $slaConfig->calculateResponseDueDate($createdAt);
        $ticket->sla_due_at = $slaConfig->calculateResolutionDueDate($createdAt);
        $ticket->escalation_rules = $slaConfig->escalation_rules;

        $ticket->save();
    }

    /**
     * Calculate due date based on priority (fallback method)
     */
    public function calculateDueDate(string $priority, ?Carbon $createdAt = null): Carbon
    {
        $createdAt = $createdAt ?? now();

        $hours = match ($priority) {
            'critical' => 2,
            'high' => 8,
            'normal' => 24,
            'medium' => 24,
            'low' => 72,
            default => 24,
        };

        return $createdAt->copy()->addHours($hours);
    }

    /**
     * Mark first response time
     */
    public function markFirstResponse(Ticket $ticket): void
    {
        if (!$ticket->sla_first_response_at) {
            $ticket->sla_first_response_at = now();
            $ticket->save();

            // Check if response was on time
            if ($ticket->sla_response_due_at && $ticket->sla_first_response_at <= $ticket->sla_response_due_at) {
                Log::info("Ticket {$ticket->id} responded on time");
            } else {
                Log::warning("Ticket {$ticket->id} response was late", [
                    'due_at' => $ticket->sla_response_due_at,
                    'responded_at' => $ticket->sla_first_response_at,
                ]);
            }
        }
    }

    /**
     * Mark resolution time
     */
    public function markResolution(Ticket $ticket): void
    {
        if (!$ticket->sla_resolved_at && $ticket->status === 'closed') {
            $ticket->sla_resolved_at = now();
            $ticket->closed_at = now();
            $ticket->save();

            // Check if resolution was on time
            if ($ticket->sla_due_at && $ticket->sla_resolved_at <= $ticket->sla_due_at) {
                Log::info("Ticket {$ticket->id} resolved on time");
            } else {
                Log::warning("Ticket {$ticket->id} resolution was late", [
                    'due_at' => $ticket->sla_due_at,
                    'resolved_at' => $ticket->sla_resolved_at,
                ]);
            }
        }
    }

    /**
     * Check for overdue tickets and handle them
     */
    public function checkOverdue(): void
    {
        $overdueTickets = Ticket::where('status', '!=', 'closed')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->get();

        foreach ($overdueTickets as $ticket) {
            $this->handleOverdue($ticket);
        }

        // Check for overdue responses
        $responseOverdueTickets = Ticket::where('status', '!=', 'closed')
            ->whereNull('sla_first_response_at')
            ->whereNotNull('sla_response_due_at')
            ->where('sla_response_due_at', '<', now())
            ->get();

        foreach ($responseOverdueTickets as $ticket) {
            $this->handleResponseOverdue($ticket);
        }
    }

    /**
     * Check and handle escalations
     */
    public function checkEscalations(): void
    {
        $tickets = Ticket::where('status', '!=', 'closed')
            ->whereNotNull('sla_due_at')
            ->whereNotNull('escalation_rules')
            ->get();

        foreach ($tickets as $ticket) {
            $this->checkTicketEscalation($ticket);
        }
    }

    public function sendReminders(): void
    {
        $tickets = Ticket::where('status', '!=', 'closed')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '>', now())
            ->where(function ($q) {
                $q->whereNull('sla_reminder_sent_at')
                    ->orWhere('sla_reminder_sent_at', '<', now()->subHours(24));
            })
            ->get();

        foreach ($tickets as $ticket) {
            $hoursUntilDue = now()->diffInHours($ticket->sla_due_at, false);

            if ($hoursUntilDue <= 4 && $hoursUntilDue > 0) {
                $this->sendReminder($ticket);
            }
        }
    }

    /**
     * Handle overdue ticket resolution
     */
    private function handleOverdue(Ticket $ticket): void
    {
        $overdueMinutes = now()->diffInMinutes($ticket->sla_due_at);

        // Notify assignee
        if ($ticket->assigned_to) {
            $this->notificationService->create(
                $ticket->assigned_to,
                'ticket.overdue',
                [
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'overdue_minutes' => $overdueMinutes,
                    'overdue_hours' => round($overdueMinutes / 60, 2),
                    'url' => "/tickets/{$ticket->id}",
                ]
            );
        }

        // Notify creator
        $this->notificationService->create(
            $ticket->created_by,
            'ticket.overdue',
            [
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'overdue_minutes' => $overdueMinutes,
                'overdue_hours' => round($overdueMinutes / 60, 2),
                'url' => "/tickets/{$ticket->id}",
            ]
        );

        // Trigger escalation if rules exist
        if ($ticket->escalation_rules) {
            $this->escalateTicket($ticket, 1);
        }
    }

    /**
     * Handle overdue response
     */
    private function handleResponseOverdue(Ticket $ticket): void
    {
        $overdueMinutes = now()->diffInMinutes($ticket->sla_response_due_at);

        // Notify assignee
        if ($ticket->assigned_to) {
            $this->notificationService->create(
                $ticket->assigned_to,
                'ticket.response_overdue',
                [
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'overdue_minutes' => $overdueMinutes,
                    'url' => "/tickets/{$ticket->id}",
                ]
            );
        }

        // Notify creator
        $this->notificationService->create(
            $ticket->created_by,
            'ticket.response_overdue',
            [
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'overdue_minutes' => $overdueMinutes,
                'url' => "/tickets/{$ticket->id}",
            ]
        );
    }

    /**
     * Check if ticket needs escalation
     */
    private function checkTicketEscalation(Ticket $ticket): void
    {
        if (!$ticket->escalation_rules || !is_array($ticket->escalation_rules)) {
            return;
        }

        $now = now();
        $escalationLevel = $ticket->escalation_rules['current_level'] ?? 1;

        // Check each escalation level
        foreach ($ticket->escalation_rules as $level => $rule) {
            if ($level === 'current_level' || !is_numeric($level)) {
                continue;
            }

            $level = (int) $level;
            if ($level <= $escalationLevel) {
                continue; // Already escalated to this level
            }

            $thresholdMinutes = $rule['threshold_minutes'] ?? 0;
            if ($thresholdMinutes <= 0) {
                continue;
            }

            $minutesUntilDue = $now->diffInMinutes($ticket->sla_due_at, false);

            if ($minutesUntilDue <= $thresholdMinutes && $minutesUntilDue > 0) {
                $this->escalateTicket($ticket, $level);
                break;
            }
        }
    }

    /**
     * Escalate ticket to a specific level
     */
    private function escalateTicket(Ticket $ticket, int $level): void
    {
        if (!$ticket->escalation_rules || !isset($ticket->escalation_rules[$level])) {
            return;
        }

        $rule = $ticket->escalation_rules[$level];
        $action = $rule['action'] ?? 'notify';

        // Update escalation level
        $escalationRules = $ticket->escalation_rules;
        $escalationRules['current_level'] = $level;
        $ticket->escalation_rules = $escalationRules;
        $ticket->save();

        match ($action) {
            'notify' => $this->escalateNotify($ticket, $rule),
            'reassign' => $this->escalateReassign($ticket, $rule),
            'increase_priority' => $this->escalateIncreasePriority($ticket, $rule),
            default => Log::warning("Unknown escalation action: {$action}", ['ticket_id' => $ticket->id]),
        };
    }

    /**
     * Escalate by notifying users
     */
    private function escalateNotify(Ticket $ticket, array $rule): void
    {
        $userIds = $rule['notify_user_ids'] ?? [];
        $message = $rule['message'] ?? "Ticket #{$ticket->id} has been escalated";

        foreach ($userIds as $userId) {
            $this->notificationService->create(
                $userId,
                'ticket.escalated',
                [
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'message' => $message,
                    'url' => "/tickets/{$ticket->id}",
                ]
            );
        }
    }

    /**
     * Escalate by reassigning
     */
    private function escalateReassign(Ticket $ticket, array $rule): void
    {
        $newAssigneeId = $rule['reassign_to_user_id'] ?? null;

        if ($newAssigneeId) {
            $oldAssigneeId = $ticket->assigned_to;
            $ticket->assigned_to = $newAssigneeId;
            $ticket->save();

            // Notify new assignee
            $this->notificationService->create(
                $newAssigneeId,
                'ticket.assigned',
                [
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'escalated' => true,
                    'url' => "/tickets/{$ticket->id}",
                ]
            );

            // Notify old assignee if different
            if ($oldAssigneeId && $oldAssigneeId != $newAssigneeId) {
                $this->notificationService->create(
                    $oldAssigneeId,
                    'ticket.reassigned',
                    [
                        'ticket_id' => $ticket->id,
                        'ticket_title' => $ticket->title,
                        'url' => "/tickets/{$ticket->id}",
                    ]
                );
            }
        }
    }

    /**
     * Escalate by increasing priority
     */
    private function escalateIncreasePriority(Ticket $ticket, array $rule): void
    {
        $newPriority = $rule['new_priority'] ?? null;

        if ($newPriority && $this->isPriorityHigher($newPriority, $ticket->priority)) {
            $oldPriority = $ticket->priority;
            $ticket->priority = $newPriority;
            
            // Recalculate SLA with new priority
            $this->applySlaToTicket($ticket);

            Log::info("Ticket {$ticket->id} priority escalated from {$oldPriority} to {$newPriority}");
        }
    }

    /**
     * Check if priority is higher
     */
    private function isPriorityHigher(string $newPriority, string $currentPriority): bool
    {
        $priorityOrder = ['low' => 1, 'normal' => 2, 'medium' => 2, 'high' => 3, 'critical' => 4];

        return ($priorityOrder[$newPriority] ?? 0) > ($priorityOrder[$currentPriority] ?? 0);
    }

    private function sendReminder(Ticket $ticket): void
    {
        if ($ticket->assigned_to) {
            $this->notificationService->create(
                $ticket->assigned_to,
                'ticket.sla_reminder',
                [
                    'ticket_id' => $ticket->id,
                    'ticket_title' => $ticket->title,
                    'hours_until_due' => now()->diffInHours($ticket->sla_due_at, false),
                    'url' => "/tickets/{$ticket->id}",
                ]
            );
        }

        $ticket->update(['sla_reminder_sent_at' => now()]);
    }

    /**
     * Get comprehensive SLA report
     */
    public function getSlaReport(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now();

        $tickets = Ticket::whereBetween('created_at', [$from, $to])->get();

        $total = $tickets->count();
        $resolved = $tickets->where('status', 'closed')->count();
        $overdue = $tickets->filter(fn($t) => $t->isOverdue())->count();
        $responseOverdue = $tickets->filter(fn($t) => $t->isResponseOverdue())->count();
        $onTime = $tickets->filter(fn($t) => 
            $t->sla_resolved_at && $t->sla_due_at && $t->sla_resolved_at <= $t->sla_due_at
        )->count();
        $responseOnTime = $tickets->filter(fn($t) => 
            $t->sla_first_response_at && $t->sla_response_due_at && 
            $t->sla_first_response_at <= $t->sla_response_due_at
        )->count();

        $avgResolutionTime = $tickets->whereNotNull('sla_resolved_at')
            ->avg(fn($t) => $t->created_at->diffInHours($t->sla_resolved_at));

        $avgResponseTime = $tickets->whereNotNull('sla_first_response_at')
            ->avg(fn($t) => $t->created_at->diffInMinutes($t->sla_first_response_at));

        // Group by SLA level
        $bySlaLevel = $tickets->groupBy('sla_level')->map(function ($group) {
            $total = $group->count();
            $onTime = $group->filter(fn($t) => 
                $t->sla_resolved_at && $t->sla_due_at && $t->sla_resolved_at <= $t->sla_due_at
            )->count();

            return [
                'total' => $total,
                'on_time' => $onTime,
                'compliance_percent' => $total > 0 ? round(($onTime / $total) * 100, 2) : 0,
            ];
        });

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'summary' => [
                'total' => $total,
                'resolved' => $resolved,
                'overdue' => $overdue,
                'response_overdue' => $responseOverdue,
                'on_time' => $onTime,
                'response_on_time' => $responseOnTime,
                'avg_resolution_hours' => round($avgResolutionTime ?? 0, 2),
                'avg_response_minutes' => round($avgResponseTime ?? 0, 2),
                'sla_compliance_percent' => $total > 0 ? round(($onTime / $total) * 100, 2) : 0,
                'response_compliance_percent' => $total > 0 ? round(($responseOnTime / $total) * 100, 2) : 0,
            ],
            'by_sla_level' => $bySlaLevel,
        ];
    }

    /**
     * Get SLA report for specific ticket
     */
    public function getTicketSlaReport(Ticket $ticket): array
    {
        $compliance = $ticket->getSlaCompliance();

        return [
            'ticket_id' => $ticket->id,
            'sla_level' => $ticket->sla_level,
            'response_time_minutes' => $ticket->response_time_minutes,
            'resolution_time_minutes' => $ticket->resolution_time_minutes,
            'sla_response_due_at' => $ticket->sla_response_due_at?->toIso8601String(),
            'sla_first_response_at' => $ticket->sla_first_response_at?->toIso8601String(),
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            'sla_resolved_at' => $ticket->sla_resolved_at?->toIso8601String(),
            'compliance' => $compliance,
            'response_time_minutes' => $ticket->sla_first_response_at 
                ? $ticket->created_at->diffInMinutes($ticket->sla_first_response_at) 
                : null,
            'resolution_time_minutes' => $ticket->sla_resolved_at 
                ? $ticket->created_at->diffInMinutes($ticket->sla_resolved_at) 
                : null,
        ];
    }
}

