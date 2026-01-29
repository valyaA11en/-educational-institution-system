<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'contest_id',
        'submission_id',
        'place',
        'final_score',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'decimal:2',
            'published_at' => 'datetime',
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
}

