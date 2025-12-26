<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'term_id',
        'config_json',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}

