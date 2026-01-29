<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subgroup extends Model
{
    protected $fillable = [
        'name',
        'code',
        'group_id',
        'tenant_id',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'tenant_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the group that owns the subgroup.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the tenant that owns the subgroup.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the users in this subgroup.
     */
    public function users(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'subgroup_id');
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}