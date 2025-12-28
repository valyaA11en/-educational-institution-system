<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasTenant;

class PortfolioItem extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'tenant_id',
        'student_user_id',
        'type',
        'title',
        'description',
        'date',
        'file_path',
        'metadata_json',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'metadata_json' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}

