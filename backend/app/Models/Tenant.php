<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'timezone',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the tenant members.
     */
    public function members(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    /**
     * Get the tenant users through members relationship.
     */
    public function users()
    {
        return $this->hasManyThrough(User::class, TenantMember::class, 'tenant_id', 'id', 'id', 'user_id');
    }

    /**
     * Scope to find tenant by slug.
     */
    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }
}