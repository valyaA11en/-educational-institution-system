<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tenant;

class ScheduleVersion extends Model
{
    protected $table = 'schedule_versions';

    protected $fillable = [
        'tenant_id',
        'term_id',
        'status',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the creator of this version
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the term for this version
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * Get all schedule items for this version
     */
    public function items(): HasMany
    {
        return $this->hasMany(ScheduleItem::class, 'version_id');
    }

    /**
     * Get changelog entries for this version
     */
    public function changelog(): HasMany
    {
        return $this->hasMany(ScheduleChangelog::class, 'version_id');
    }

    /**
     * Get the tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Check if version is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if version is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if version is archived
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}
