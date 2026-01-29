<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleReplacement extends Model
{
    protected $table = 'schedule_replacements';

    protected $fillable = [
        'tenant_id',
        'schedule_item_id',
        'date',
        'new_teacher_user_id',
        'new_room_id',
        'reason',
        'approved_by',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the schedule item
     */
    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(ScheduleItem::class, 'schedule_item_id');
    }

    /**
     * Get the new teacher
     */
    public function newTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_teacher_user_id');
    }

    /**
     * Get the new room
     */
    public function newRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'new_room_id');
    }

    /**
     * Get the approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Check if replacement is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if replacement is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if replacement is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if replacement is applied
     */
    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }
}
