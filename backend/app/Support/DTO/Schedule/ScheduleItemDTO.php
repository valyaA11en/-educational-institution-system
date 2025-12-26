<?php

namespace App\Support\DTO\Schedule;

use Carbon\CarbonInterface;

readonly class ScheduleItemDTO
{
    public function __construct(
        public int $id,
        public int $versionId,
        public CarbonInterface $date,
        public int $timeSlotId,
        public int $groupId,
        public ?int $subgroupId,
        public int $subjectId,
        public int $teacherUserId,
        public int $roomId,
        public ?string $overrideReason,
    ) {
    }
}



