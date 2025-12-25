<?php

namespace App\Support\DTO\Schedule;

use Carbon\CarbonInterface;

readonly class ScheduleItemCreateDTO
{
    public function __construct(
        public CarbonInterface $date,
        public int $timeSlotId,
        public int $groupId,
        public ?int $subgroupId,
        public int $subjectId,
        public int $teacherUserId,
        public int $roomId,
        public bool $force,
        public ?string $overrideReason,
    ) {
    }
}


