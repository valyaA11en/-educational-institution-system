<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketSLA;
use Carbon\Carbon;

class SLAService
{
    /**
     * Calculate and set due_at for ticket based on SLA
     */
    public function calculateDueDate(Ticket $ticket): ?Carbon
    {
        $sla = $ticket->getSla();
        
        if (!$sla) {
            return null;
        }

        // Calculate resolution time
        $resolutionTime = (int) $sla->resolution_time_minutes;
        return Carbon::now()->addMinutes($resolutionTime);
    }

    /**
     * Check if ticket is within SLA response time
     */
    public function isWithinResponseTime(Ticket $ticket): bool
    {
        $sla = $ticket->getSla();
        
        if (!$sla) {
            return true; // No SLA = always OK
        }

        $responseTime = (int) $sla->response_time_minutes;
        $elapsed = Carbon::now()->diffInMinutes($ticket->created_at);
        
        return $elapsed <= $responseTime;
    }

    /**
     * Check if ticket is within SLA resolution time
     */
    public function isWithinResolutionTime(Ticket $ticket): bool
    {
        if (!$ticket->due_at) {
            return true; // No due date = always OK
        }

        return Carbon::now()->lte($ticket->due_at);
    }

    /**
     * Get SLA status for ticket
     */
    public function getSlaStatus(Ticket $ticket): array
    {
        $sla = $ticket->getSla();
        
        if (!$sla) {
            return [
                'has_sla' => false,
                'status' => 'no_sla',
            ];
        }

        $responseOk = $this->isWithinResponseTime($ticket);
        $resolutionOk = $this->isWithinResolutionTime($ticket);

        if (!$responseOk) {
            return [
                'has_sla' => true,
                'status' => 'response_breached',
                'sla_level' => $sla->sla_level,
                'response_time_minutes' => $sla->response_time_minutes,
                'resolution_time_minutes' => $sla->resolution_time_minutes,
            ];
        }

        if (!$resolutionOk) {
            return [
                'has_sla' => true,
                'status' => 'resolution_breached',
                'sla_level' => $sla->sla_level,
                'response_time_minutes' => $sla->response_time_minutes,
                'resolution_time_minutes' => $sla->resolution_time_minutes,
            ];
        }

        return [
            'has_sla' => true,
            'status' => 'ok',
            'sla_level' => $sla->sla_level,
            'response_time_minutes' => $sla->response_time_minutes,
            'resolution_time_minutes' => $sla->resolution_time_minutes,
        ];
    }

    /**
     * Apply SLA to ticket
     */
    public function applySla(Ticket $ticket): void
    {
        $dueAt = $this->calculateDueDate($ticket);
        if ($dueAt) {
            $ticket->due_at = $dueAt;
            $ticket->save();
        }
    }

    /**
     * Check and escalate ticket if needed
     */
    public function checkEscalation(Ticket $ticket): void
    {
        $sla = $ticket->getSla();
        
        if (!$sla || !$sla->escalation_rules) {
            return;
        }

        $status = $this->getSlaStatus($ticket);
        
        if ($status['status'] === 'response_breached' || $status['status'] === 'resolution_breached') {
            // Apply escalation rules
            $rules = $sla->escalation_rules;
            
            if (isset($rules['auto_assign_to_manager']) && $rules['auto_assign_to_manager']) {
                // Auto-assign to manager logic here
            }
            
            if (isset($rules['notify_admins']) && $rules['notify_admins']) {
                // Notify admins logic here
            }
        }
    }
}