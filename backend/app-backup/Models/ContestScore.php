<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'contest_id',
        'submission_id',
        'jury_user_id',
        'rubric_json',
        'total_score',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rubric_json' => 'array',
            'total_score' => 'decimal:2',
        ];
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ContestSubmission::class);
    }

    public function juryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jury_user_id');
    }
}

