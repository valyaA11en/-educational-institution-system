<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'group_id',
        'term_id',
        'teacher_user_id',
        'name',
        'description',
        'is_template',
        'template_id',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_template' => 'boolean',
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CurriculumPlan::class, 'template_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(CurriculumTopic::class, 'curriculum_plan_id')->orderBy('order_no');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progress(): float
    {
        $topics = $this->topics;
        if ($topics->isEmpty()) {
            return 0;
        }

        $completed = $topics->filter(function ($topic) {
            return $topic->isCompleted();
        })->count();

        return ($completed / $topics->count()) * 100;
    }
}

