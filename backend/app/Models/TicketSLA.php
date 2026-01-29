<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketSLA extends Model
{
    protected $table = 'ticket_slas';

    protected $fillable = [
        'sla_level',
        'name',
        'response_time_minutes',
        'resolution_time_minutes',
        'escalation_rules',
        'tenant_id',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'response_time_minutes' => 'integer',
        'resolution_time_minutes' => 'integer',
        'escalation_rules' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get tickets with this SLA level.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'sla_level', 'sla_level');
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}