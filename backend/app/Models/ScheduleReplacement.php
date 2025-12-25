<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleReplacement extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_item_id',
        'date',
        'new_teacher_user_id',
        'new_room_id',
        'reason',
        'approved_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(ScheduleItem::class, 'schedule_item_id');
    }

    public function newTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_teacher_user_id');
    }

    public function newRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'new_room_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

