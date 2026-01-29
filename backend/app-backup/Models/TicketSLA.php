<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketSLA extends Model
{
    use HasFactory;

    protected $table = 'ticket_slas';

    protected $fillable = [
        'name',
        'sla_level',
        'category',
        'priority',
        'response_time_minutes',
        'resolution_time_minutes',
        'escalation_rules',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'response_time_minutes' => 'integer',
            'resolution_time_minutes' => 'integer',
            'escalation_rules' => 'array',
            'enabled' => 'boolean',
        ];
    }

    /**
     * Get SLA configuration for ticket category and priority
     */
    public static function getForTicket(?string $category = null, string $priority = 'normal'): ?self
    {
        $query = self::where('enabled', true)
            ->where('priority', $priority);

        if ($category) {
            $query->where(function ($q) use ($category) {
                $q->where('category', $category)
                    ->orWhereNull('category');
            });
        } else {
            $query->whereNull('category');
        }

        return $query->orderBy('category', 'desc') // Category-specific first
            ->first();
    }

    /**
     * Calculate response due date based on creation time
     */
    public function calculateResponseDueDate(\Carbon\Carbon $createdAt): \Carbon\Carbon
    {
        return $createdAt->copy()->addMinutes($this->response_time_minutes ?? 0);
    }

    /**
     * Calculate resolution due date based on creation time
     */
    public function calculateResolutionDueDate(\Carbon\Carbon $createdAt): \Carbon\Carbon
    {
        return $createdAt->copy()->addMinutes($this->resolution_time_minutes ?? 0);
    }

    /**
     * Check if escalation is needed based on rules
     */
    public function shouldEscalate(\Carbon\Carbon $now, \Carbon\Carbon $dueAt, int $escalationLevel = 1): bool
    {
        if (!$this->escalation_rules || !isset($this->escalation_rules[$escalationLevel])) {
            return false;
        }

        $rule = $this->escalation_rules[$escalationLevel];
        $thresholdMinutes = $rule['threshold_minutes'] ?? 0;

        if ($thresholdMinutes <= 0) {
            return false;
        }

        $minutesUntilDue = $now->diffInMinutes($dueAt, false);

        // Escalate if we're within threshold minutes of due date
        return $minutesUntilDue <= $thresholdMinutes && $minutesUntilDue > 0;
    }

    /**
     * Get escalation action for level
     */
    public function getEscalationAction(int $escalationLevel): ?array
    {
        if (!$this->escalation_rules || !isset($this->escalation_rules[$escalationLevel])) {
            return null;
        }

        return $this->escalation_rules[$escalationLevel];
    }
}

