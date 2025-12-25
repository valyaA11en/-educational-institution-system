<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'priority',
        'status',
        'created_by',
        'assigned_to',
        'sla_level',
        'response_time_minutes',
        'resolution_time_minutes',
        'sla_due_at',
        'sla_response_due_at',
        'sla_first_response_at',
        'sla_resolved_at',
        'sla_reminder_sent_at',
        'escalation_rules',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'response_time_minutes' => 'integer',
            'resolution_time_minutes' => 'integer',
            'sla_due_at' => 'datetime',
            'sla_response_due_at' => 'datetime',
            'sla_first_response_at' => 'datetime',
            'sla_resolved_at' => 'datetime',
            'sla_reminder_sent_at' => 'datetime',
            'closed_at' => 'datetime',
            'escalation_rules' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * Get SLA configuration (if exists)
     */
    public function getSlaConfig(): ?TicketSLA
    {
        if (!$this->sla_level) {
            return null;
        }

        return TicketSLA::getForTicket($this->category, $this->priority);
    }

    /**
     * Check if ticket is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'closed') {
            return false;
        }

        return $this->sla_due_at && $this->sla_due_at->isPast();
    }

    /**
     * Check if response is overdue
     */
    public function isResponseOverdue(): bool
    {
        if ($this->sla_first_response_at) {
            return false; // Already responded
        }

        return $this->sla_response_due_at && $this->sla_response_due_at->isPast();
    }

    /**
     * Get SLA compliance status
     */
    public function getSlaCompliance(): array
    {
        $now = now();
        $responseCompliant = true;
        $resolutionCompliant = true;

        if ($this->sla_response_due_at) {
            $responseCompliant = !$this->isResponseOverdue();
        }

        if ($this->sla_due_at) {
            $resolutionCompliant = !$this->isOverdue();
        }

        return [
            'response_compliant' => $responseCompliant,
            'resolution_compliant' => $resolutionCompliant,
            'overall_compliant' => $responseCompliant && $resolutionCompliant,
            'response_overdue_minutes' => $this->isResponseOverdue() 
                ? $now->diffInMinutes($this->sla_response_due_at) 
                : 0,
            'resolution_overdue_minutes' => $this->isOverdue() 
                ? $now->diffInMinutes($this->sla_due_at) 
                : 0,
        ];
    }
}

