<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleItem extends Model
{
    protected $table = 'schedule_items';

    protected $fillable = [
        'tenant_id',
        'version_id',
        'date',
        'time_slot_id',
        'group_id',
        'subgroup_id',
        'subject_id',
        'teacher_user_id',
        'room_id',
        'override_reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the version this item belongs to
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'version_id');
    }

    /**
     * Get the group
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    /**
     * Get the subgroup
     */
    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class, 'subgroup_id');
    }

    /**
     * Get the subject
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * Get the teacher
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    /**
     * Get the room
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    /**
     * Get the time slot
     */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class, 'time_slot_id');
    }

    /**
     * Get the creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get changelog entries for this item
     */
    public function changelog(): HasMany
    {
        return $this->hasMany(ScheduleChangelog::class, 'schedule_item_id');
    }
}
