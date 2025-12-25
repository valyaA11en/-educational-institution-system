<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Risk extends Model
{
    use HasFactory;

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
        'resolution_notes',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'decimal:2',
            'factors' => 'array',
            'metadata' => 'array',
            'calculated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the entity that has the risk (polymorphic)
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get term data (using DB since Term model might not exist)
     */
    public function getTermData(): ?object
    {
        if (!$this->term_id) {
            return null;
        }
        return DB::table('terms')->where('id', $this->term_id)->first();
    }

    /**
     * Get user who calculated the risk
     */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * Get user who resolved the risk
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Check if risk is resolved
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Check if risk is active
     */
    public function isActive(): bool
    {
        return !$this->isResolved() && $this->risk_level !== 'none';
    }

    /**
     * Scope for active risks
     */
    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at')
            ->where('risk_level', '!=', 'none');
    }

    /**
     * Scope for resolved risks
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Scope by risk level
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('risk_level', $level);
    }

    /**
     * Scope by entity type
     */
    public function scopeByEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope for high priority risks
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }
}

