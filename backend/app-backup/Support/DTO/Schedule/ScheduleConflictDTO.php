<?php

namespace App\Support\DTO\Schedule;

readonly class ScheduleConflictDTO
{
    public function __construct(
        public string $type, // room|teacher|group
        public int $entityId,
        public string $date,
        public int $timeSlotId,
        public string $message,
    ) {
    }
}









