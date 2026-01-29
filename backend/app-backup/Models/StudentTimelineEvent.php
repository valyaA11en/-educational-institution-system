<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasTenant;

class StudentTimelineEvent extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'student_timeline_events';

    protected $fillable = [
        'tenant_id',
        'student_user_id',
        'event_type',
        'event_date',
        'title',
        'description',
        'related_entity_type',
        'related_entity_id',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'payload_json' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function relatedEntity()
    {
        if (!$this->related_entity_type || !$this->related_entity_id) {
            return null;
        }

        if (!class_exists($this->related_entity_type)) {
            return null;
        }

        return $this->related_entity_type::find($this->related_entity_id);
    }
}


