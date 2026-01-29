<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_plan_id',
        'order',
        'order_no',
        'title',
        'description',
        'hours_total',
        'hours',
        'hours_lecture',
        'hours_practice',
        'hours_lab',
        'control_type',
        'planned_date_from',
        'planned_date_to',
    ];

    protected function casts(): array
    {
        return [
            'planned_date_from' => 'date',
            'planned_date_to' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CurriculumPlan::class, 'curriculum_plan_id');
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Lesson::class, 'curriculum_topic_lessons', 'topic_id', 'lesson_id');
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(Assignment::class, 'curriculum_topic_assignments', 'topic_id', 'assignment_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(KtpTopicLink::class, 'ktp_topic_id');
    }

    public function linkedLessons(): HasMany
    {
        return $this->hasMany(KtpTopicLink::class, 'ktp_topic_id')->whereNotNull('lesson_id');
    }

    public function linkedAssignments(): HasMany
    {
        return $this->hasMany(KtpTopicLink::class, 'ktp_topic_id')->whereNotNull('assignment_id');
    }

    public function linkedMaterials(): HasMany
    {
        return $this->hasMany(KtpTopicLink::class, 'ktp_topic_id')->whereNotNull('material_id');
    }

    public function isCompleted(): bool
    {
        $lessonsCount = $this->lessons()->count();
        $assignmentsCount = $this->assignments()->count();
        
        // TODO: more sophisticated completion logic
        return $lessonsCount > 0 || $assignmentsCount > 0;
    }

    public function hoursSpent(): int
    {
        $lessons = $this->lessons;
        $totalHours = 0;
        
        foreach ($lessons as $lesson) {
            // TODO: calculate from lesson duration
            $totalHours += 2; // placeholder
        }
        
        return $totalHours;
    }
}

