<?php

namespace App\Support\DTO\Journal;

readonly class GradeCreateDTO
{
    public function __construct(
        public ?int $lessonId,
        public ?int $assignmentId,
        public int $studentId,
        public int $value,
        public int $weight,
        public ?string $gradeType,
        public ?string $comment,
    ) {
    }
}


