<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradePeriodSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_user_id',
        'subject_id',
        'term_id',
        'avg_value',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'avg_value' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}







