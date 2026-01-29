<?php

namespace App\Services\Outbox;

use App\Models\OutboxEvent;
use Illuminate\Support\Str;

class OutboxService
{
    public function record(
        string $eventType,
        ?int $actorUserId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
        ?string $idempotencyKey = null
    ): OutboxEvent {
        $idempotencyKey = $idempotencyKey ?? $this->generateIdempotencyKey($eventType, $entityType, $entityId, $payload);

        return OutboxEvent::create([
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload_json' => $payload,
            'idempotency_key' => $idempotencyKey,
            'status' => 'new',
            'attempts' => 0,
        ]);
    }

    protected function generateIdempotencyKey(
        string $eventType,
        ?string $entityType,
        ?int $entityId,
        array $payload
    ): string {
        $data = [
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        return hash('sha256', json_encode($data));
    }
}








