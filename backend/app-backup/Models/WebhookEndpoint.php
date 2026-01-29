<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'secret',
        'enabled',
        'event_types',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'event_types' => 'array',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function matchesEventType(string $eventType): bool
    {
        return in_array($eventType, $this->event_types ?? [], true);
    }
}

