<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContestRubric extends Model
{
    use HasFactory;

    protected $fillable = [
        'contest_id',
        'title',
        'criteria_json',
    ];

    protected function casts(): array
    {
        return [
            'criteria_json' => 'array',
        ];
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

}

