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
        'user_id',
        'term_id',
        'risk_type',
        'level',
        'score',
        'details_json',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'details_json' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    /**
     * Get the student user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * Scope by risk level
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope by risk type
     */
    public function scopeByRiskType($query, string $riskType)
    {
        return $query->where('risk_type', $riskType);
    }

    /**
     * Scope for high priority risks (red level)
     */
    public function scopeHighPriority($query)
    {
        return $query->where('level', 'red');
    }

    /**
     * Scope for active risks (not green)
     */
    public function scopeActive($query)
    {
        return $query->where('level', '!=', 'green');
    }
}

