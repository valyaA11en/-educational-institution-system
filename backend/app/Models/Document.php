<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'signed_by',
        'signed_at',
        'verify_hash',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'data_json' => 'array',
            'signed_at' => 'datetime',
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

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(DocumentRoute::class)->orderBy('step_no');
    }

    public function acks(): HasMany
    {
        return $this->hasMany(DocumentAck::class);
    }

    public function currentStep(): ?DocumentRoute
    {
        return $this->routes()
            ->where('status', 'pending')
            ->orderBy('step_no')
            ->first();
    }
}
