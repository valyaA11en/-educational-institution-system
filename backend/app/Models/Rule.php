<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'enabled',
        'scope',
        'conditions_json',
        'actions_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'conditions_json' => 'array',
            'actions_json' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}







