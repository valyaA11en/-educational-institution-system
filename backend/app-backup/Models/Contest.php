<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contest extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_at',
        'end_at',
        'visibility_scope',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(ContestTarget::class);
    }

    public function jury(): HasMany
    {
        return $this->hasMany(ContestJury::class);
    }

    public function rubrics(): HasMany
    {
        return $this->hasMany(ContestRubric::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ContestSubmission::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ContestResult::class)->orderBy('place');
    }
}

