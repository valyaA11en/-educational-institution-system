<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasTenant;

class FailedTopicsAnalysis extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'failed_topics_analysis';

    protected $fillable = [
        'tenant_id',
        'student_user_id',
        'subject_id',
        'ktp_topic_id',
        'topic_name',
        'failed_attempts',
        'average_grade',
        'last_attempt_date',
        'details_json',
    ];

    protected function casts(): array
    {
        return [
            'average_grade' => 'decimal:2',
            'last_attempt_date' => 'date',
            'details_json' => 'array',
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

