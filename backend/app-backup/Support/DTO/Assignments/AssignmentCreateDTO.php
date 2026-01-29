<?php

namespace App\Support\DTO\Assignments;

use Carbon\CarbonInterface;

readonly class AssignmentCreateDTO
{
    /**
     * @param array<int, string>|null $allowedTypes
     */
    public function __construct(
        public int $subjectId,
        public int $teacherUserId,
        public string $title,
        public ?string $description,
        public ?CarbonInterface $dueAt,
        public int $maxAttempts,
        public ?int $maxFileSize,
        public ?array $allowedTypes,
        public string $visibilityScope,
    ) {
    }
}









