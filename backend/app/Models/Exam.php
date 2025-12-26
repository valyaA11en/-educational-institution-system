<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'term_id',
        'type',
        'title',
        'subject_id',
        'group_id',
        'date_at',
        'room_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ExamCommission::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }
}

