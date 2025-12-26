<?php

namespace App\Support\DTO\Schedule;

use Carbon\CarbonInterface;

readonly class ScheduleVersionDTO
{
    /**
     * @param array<int, ScheduleItemDTO> $items
     */
    public function __construct(
        public int $id,
        public int $termId,
        public string $status,
        public int $createdBy,
        public ?CarbonInterface $publishedAt,
        public CarbonInterface $createdAt,
        public CarbonInterface $updatedAt,
        public array $items = [],
    ) {
    }
}



