<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Risk extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'risk_level',
        'risk_score',
        'factors',
        'metadata',
        'term_id',
        'calculated_by',
        'calculated_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'tenant_id',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'risk_score' => 'decimal:2',
        'factors' => 'array',
        'metadata' => 'array',
        'term_id' => 'integer',
        'calculated_by' => 'integer',
        'resolved_by' => 'integer',
        'tenant_id' => 'integer',
        'calculated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the entity that has this risk (polymorphic).
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    /**
     * Get the user who resolved the risk.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the term.
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * Get the user who calculated the risk.
     */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * Scope to filter by risk level.
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('risk_level', $level);
    }

    /**
     * Scope to filter active risks.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope to filter resolved risks.
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
