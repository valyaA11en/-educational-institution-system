<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasTenant;

class StudentTimeline extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'student_timeline';

    protected $fillable = [
        'tenant_id',
        'student_user_id',
        'event_type',
        'entity_type',
        'entity_id',
        'metadata_json',
        'event_date',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'event_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function entity()
    {
        if (!$this->entity_type || !$this->entity_id) {
            return null;
        }

        if (!class_exists($this->entity_type)) {
            return null;
        }

        return $this->entity_type::find($this->entity_id);
    }
}
}

