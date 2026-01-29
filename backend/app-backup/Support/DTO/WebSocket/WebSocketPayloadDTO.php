<?php

namespace App\Support\DTO\WebSocket;

readonly class WebSocketPayloadDTO
{
    public function __construct(
        public int $eventId,
        public string $eventType,
        public array $payload,
        public string $createdAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'eventType' => $this->eventType,
            'payload' => $this->payload,
            'createdAt' => $this->createdAt,
        ];
    }

    public static function fromOutboxEvent(\App\Models\OutboxEvent $event): self
    {
        return new self(
            eventId: $event->id,
            eventType: $event->event_type,
            payload: $event->payload_json,
            createdAt: $event->created_at->toIso8601String()
        );
    }
}








