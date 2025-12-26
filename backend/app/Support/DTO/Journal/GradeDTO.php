<?php

namespace App\Support\DTO\Journal;

use Carbon\CarbonInterface;

readonly class GradeDTO
{
    public function __construct(
        public int $id,
        public ?int $lessonId,
        public ?int $assignmentId,
        public int $studentId,
        public int $value,
        public int $weight,
        public ?string $gradeType,
        public ?string $comment,
        public int $createdBy,
        public CarbonInterface $createdAt,
        public CarbonInterface $updatedAt,
    ) {
    }
}



