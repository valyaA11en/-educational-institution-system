<?php

namespace App\Support\DTO\Journal;

use Carbon\CarbonInterface;

readonly class AttendanceDTO
{
    public function __construct(
        public int $id,
        public int $lessonId,
        public int $studentUserId,
        public string $status,
        public ?string $reason,
        public int $createdBy,
        public CarbonInterface $createdAt,
        public CarbonInterface $updatedAt,
    ) {
    }
}


