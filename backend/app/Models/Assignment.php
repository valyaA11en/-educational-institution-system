<?php

namespace App\Models;

use App\Models\Scopes\VisibleToUserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'teacher_user_id',
        'title',
        'description',
        'due_at',
        'max_attempts',
        'max_file_size',
        'allowed_types',
        'visibility_scope',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'allowed_types' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToUserScope());
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AssignmentTarget::class);
    }
}

