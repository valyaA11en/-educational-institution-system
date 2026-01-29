<?php

namespace App\Support\DTO\Chat;

use Carbon\CarbonInterface;

readonly class ChatMessageDTO
{
    /**
     * @param array<string, mixed>|null $attachments
     */
    public function __construct(
        public int $id,
        public int $threadId,
        public int $userId,
        public string $text,
        public ?array $attachments,
        public ?CarbonInterface $deletedAt,
        public CarbonInterface $createdAt,
        public CarbonInterface $updatedAt,
    ) {
    }
}









