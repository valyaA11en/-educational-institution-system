<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasTenant;

class ScheduleItem extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
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
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'version_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class, 'subgroup_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(ScheduleReplacement::class, 'schedule_item_id');
    }
}







