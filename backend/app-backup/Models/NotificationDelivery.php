<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'user_id',
        'status',
        'attempts',
        'sent_at',
        'acknowledged_at',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


