<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WsEventDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'outbox_event_id',
        'status',
        'sent_at',
        'acked_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'acked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outboxEvent(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class);
    }
}

