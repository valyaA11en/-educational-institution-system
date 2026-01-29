<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $fillable = [
        'name',
        'code',
        'tenant_id',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the group.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the group members.
     */
    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Get the users in this group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members');
    }

    /**
     * Get the subgroups in this group.
     */
    public function subgroups(): HasMany
    {
        return $this->hasMany(Subgroup::class);
    }

    /**
     * Get the schedule items for this group.
     */
    public function scheduleItems(): HasMany
    {
        return $this->hasMany(ScheduleItem::class);
    }

    /**
     * Get the assignments targeted at this group.
     */
    public function assignmentTargets(): HasMany
    {
        return $this->hasMany(AssignmentTarget::class);
    }

    /**
     * Get the material targets for this group.
     */
    public function materialTargets(): HasMany
    {
        return $this->hasMany(MaterialTarget::class);
    }

    /**
     * Get the curriculum plans for this group.
     */
    public function curriculumPlans(): HasMany
    {
        return $this->hasMany(CurriculumPlan::class);
    }

    /**
     * Get the exams for this group.
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}