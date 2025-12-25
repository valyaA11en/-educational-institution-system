<?php

namespace App\Support\DTO\Chat;

use Carbon\CarbonInterface;

readonly class ChatThreadDTO
{
    /**
     * @param array<int, int> $memberIds
     */
    public function __construct(
        public int $id,
        public string $type,
        public ?int $groupId,
        public ?int $subjectId,
        public int $createdBy,
        public array $memberIds,
        public CarbonInterface $createdAt,
        public CarbonInterface $updatedAt,
    ) {
    }
}


