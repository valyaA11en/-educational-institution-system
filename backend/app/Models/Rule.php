<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rule extends Model
{
    protected $fillable = [
        'name',
        'scope',
        'conditions_json',
        'actions_json',
        'enabled',
        'created_by',
    ];

    protected $casts = [
        'conditions_json' => 'array',
        'actions_json' => 'array',
        'enabled' => 'boolean',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get conditions (alias for conditions_json)
     */
    public function getConditionsAttribute()
    {
        return $this->conditions_json ?? [];
    }

    /**
     * Get actions (alias for actions_json)
     */
    public function getActionsAttribute()
    {
        return $this->actions_json ?? [];
    }

    /**
     * Get the creator user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to filter enabled rules.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to filter by scope.
     */
    public function scopeForScope($query, $scope)
    {
        return $query->where('scope', $scope);
    }
}