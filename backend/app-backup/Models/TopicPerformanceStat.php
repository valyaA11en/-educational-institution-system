<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasTenant;

class TopicPerformanceStat extends Model
{
    use HasFactory, HasTenant;
    
    // Отключаем проверку связи для term, если модель не существует

    protected $table = 'topic_performance_stats';

    protected $fillable = [
        'tenant_id',
        'subject_id',
        'ktp_topic_id',
        'term_id',
        'students_total',
        'students_failed',
        'fail_percent',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'students_total' => 'integer',
            'students_failed' => 'integer',
            'fail_percent' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CurriculumTopic::class, 'ktp_topic_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}

