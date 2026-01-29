<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMember extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_in_tenant',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the member.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user that belongs to the tenant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the member is an owner.
     */
    public function isOwner(): bool
    {
        return $this->role_in_tenant === 'owner';
    }

    /**
     * Check if the member is an admin.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role_in_tenant, ['owner', 'admin']);
    }

    /**
     * Scope to get only owners.
     */
    public function scopeOwners($query)
    {
        return $query->where('role_in_tenant', 'owner');
    }

    /**
     * Scope to get only admins (including owners).
     */
    public function scopeAdmins($query)
    {
        return $query->whereIn('role_in_tenant', ['owner', 'admin']);
    }
}