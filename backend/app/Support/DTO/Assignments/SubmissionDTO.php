<?php

namespace App\Support\DTO\Assignments;

use Carbon\CarbonInterface;

readonly class SubmissionDTO
{
    /**
     * @param array<int, int> $fileIds
     */
    public function __construct(
        public int $id,
        public int $assignmentId,
        public int $studentUserId,
        public string $status,
        public ?CarbonInterface $submittedAt,
        public ?string $text,
        public array $fileIds,
        public CarbonInterface $createdAt,
        public CarbonInterface $updatedAt,
    ) {
    }
}


