<?php

namespace App\Support\DTO\Notifications;

use Carbon\CarbonInterface;

readonly class NotificationDTO
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $id,
        public string $type,
        public array $payload,
        public CarbonInterface $createdAt,
        public ?CarbonInterface $readAt,
    ) {
    }
}


