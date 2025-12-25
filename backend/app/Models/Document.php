<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'number',
        'date',
        'status',
        'template_id',
        'data_json',
        'created_by',
        'verify_hash',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'data_json' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

