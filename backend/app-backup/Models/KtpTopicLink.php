<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KtpTopicLink extends Model
{
    use HasFactory;

    protected $table = 'ktp_topic_links';

    protected $fillable = [
        'ktp_topic_id',
        'lesson_id',
        'assignment_id',
        'material_id',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CurriculumTopic::class, 'ktp_topic_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}







