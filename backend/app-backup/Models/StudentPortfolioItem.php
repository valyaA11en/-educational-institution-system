<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasTenant;

class StudentPortfolioItem extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'student_portfolio_items';

    protected $fillable = [
        'tenant_id',
        'student_user_id',
        'type',
        'title',
        'description',
        'related_entity_type',
        'related_entity_id',
        'file_id',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function relatedEntity(): MorphTo
    {
        return $this->morphTo('related_entity', 'related_entity_type', 'related_entity_id');
    }
}


