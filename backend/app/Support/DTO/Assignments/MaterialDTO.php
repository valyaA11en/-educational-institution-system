<?php

namespace App\Support\DTO\Assignments;

use Carbon\CarbonInterface;

readonly class MaterialDTO
{
    /**
     * @param array<int, int> $groupIds
     * @param array<int, int> $subgroupIds
     * @param array<int, int> $studentIds
     */
    public function __construct(
        public int $id,
        public int $subjectId,
        public string $title,
        public ?string $content,
        public string $visibilityScope,
        public int $createdBy,
        public array $groupIds = [],
        public array $subgroupIds = [],
        public array $studentIds = [],
        public CarbonInterface $createdAt,
        public CarbonInterface $updatedAt,
    ) {
    }
}



