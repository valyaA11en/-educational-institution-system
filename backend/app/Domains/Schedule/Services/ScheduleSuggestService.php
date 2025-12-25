<?php

namespace App\Domains\Schedule\Services;

use App\Models\Room;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ScheduleSuggestService
{
    public function __construct(
        private ScheduleConflictService $conflictService
    ) {}

    /**
     * Suggest available rooms for a time slot
     *
     * @param CarbonImmutable $date
     * @param int $timeSlotId
     * @param int|null $versionId
     * @param array|null $required ['type' => string, 'min_capacity' => int, 'attributes' => array]
     * @return array
     */
    public function suggestRooms(
        CarbonImmutable $date,
        int $timeSlotId,
        ?int $versionId = null,
        ?array $required = null
    ): array {
        $dateStr = $date->format('Y-m-d');

        // Get version IDs to check (current version + published version if draft)
        $versionIdsToCheck = [];
        if ($versionId) {
            $version = ScheduleVersion::find($versionId);
            if ($version) {
                $versionIdsToCheck[] = $versionId;
                if ($version->status === 'draft') {
                    $publishedVersion = ScheduleVersion::where('term_id', $version->term_id)
                        ->where('status', 'published')
                        ->first();
                    if ($publishedVersion) {
                        $versionIdsToCheck[] = $publishedVersion->id;
                    }
                }
            }
        } else {
            // If no version specified, check all published versions
            $publishedVersions = ScheduleVersion::where('status', 'published')
                ->pluck('id')
                ->toArray();
            $versionIdsToCheck = $publishedVersions;
        }

        // If no versions to check, return all rooms (no conflicts)
        if (empty($versionIdsToCheck)) {
            $versionIdsToCheck = [-1]; // Non-existent ID to make query work
        }

        // Get occupied room IDs
        if ($versionIdsToCheck === [-1]) {
            $occupiedRoomIds = [];
        } else {
            $occupiedRoomIds = ScheduleItem::whereIn('version_id', $versionIdsToCheck)
                ->where('date', $dateStr)
                ->where('time_slot_id', $timeSlotId)
                ->pluck('room_id')
                ->toArray();
        }

        // Build query for available rooms
        $query = Room::query()
            ->whereNotIn('id', $occupiedRoomIds);

        // Apply filters
        if ($required) {
            if (isset($required['min_capacity'])) {
                $query->where('capacity', '>=', $required['min_capacity']);
            }

            if (isset($required['type'])) {
                // TODO: if rooms table has 'type' column, filter by it
            }

            if (isset($required['attributes']) && !empty($required['attributes'])) {
                // Filter by JSONB attributes
                foreach ($required['attributes'] as $key => $value) {
                    $query->whereJsonContains('attributes->' . $key, $value);
                }
            }
        }

        $rooms = $query->get();

        // Sort by:
        // 1. Best attribute match (if specified) - priority
        // 2. Closest to min_capacity (if specified)
        if ($required) {
            $rooms = $rooms->sortBy(function ($room) use ($required) {
                $score = 0;

                // Attribute match score: more matches = better (higher priority)
                if (isset($required['attributes']) && !empty($required['attributes'])) {
                    $roomAttrs = $room->attributes ?? [];
                    $matchCount = 0;
                    $totalRequired = count($required['attributes']);
                    foreach ($required['attributes'] as $key => $value) {
                        if (isset($roomAttrs[$key]) && $roomAttrs[$key] === $value) {
                            $matchCount++;
                        }
                    }
                    // Perfect match = 0, no match = totalRequired * 10000
                    $score += ($totalRequired - $matchCount) * 10000;
                }

                // Capacity score: closer to min_capacity is better (lower priority)
                if (isset($required['min_capacity'])) {
                    $diff = abs($room->capacity - $required['min_capacity']);
                    $score += $diff; // Lower is better
                }

                return $score;
            })->values();
        } else {
            // Default sort by capacity (ascending)
            $rooms = $rooms->sortBy('capacity')->values();
        }

        return $rooms->map(function ($room) {
            return [
                'id' => $room->id,
                'name' => $room->name,
                'code' => $room->code,
                'capacity' => $room->capacity,
                'attributes' => $room->attributes,
            ];
        })->toArray();
    }

    /**
     * Suggest available teachers for a time slot
     *
     * @param CarbonImmutable $date
     * @param int $timeSlotId
     * @param int $subjectId
     * @param int $groupId
     * @param int|null $subgroupId
     * @param int|null $versionId
     * @return array
     */
    public function suggestTeachers(
        CarbonImmutable $date,
        int $timeSlotId,
        int $subjectId,
        int $groupId,
        ?int $subgroupId = null,
        ?int $versionId = null
    ): array {
        $dateStr = $date->format('Y-m-d');

        // Get version IDs to check
        $versionIdsToCheck = [];
        if ($versionId) {
            $version = ScheduleVersion::find($versionId);
            if ($version) {
                $versionIdsToCheck[] = $versionId;
                if ($version->status === 'draft') {
                    $publishedVersion = ScheduleVersion::where('term_id', $version->term_id)
                        ->where('status', 'published')
                        ->first();
                    if ($publishedVersion) {
                        $versionIdsToCheck[] = $publishedVersion->id;
                    }
                }
            }
        } else {
            $publishedVersions = ScheduleVersion::where('status', 'published')
                ->pluck('id')
                ->toArray();
            $versionIdsToCheck = $publishedVersions;
        }

        // If no versions to check, return all teachers (no conflicts)
        if (empty($versionIdsToCheck)) {
            $versionIdsToCheck = [-1]; // Non-existent ID to make query work
        }

        // Get teachers assigned to this subject+group(+subgroup)
        $teacherQuery = DB::table('teacher_subject_group')
            ->where('subject_id', $subjectId)
            ->where('group_id', $groupId);

        if ($subgroupId !== null) {
            // Match exact subgroup or null (whole group assignment)
            $teacherQuery->where(function ($q) use ($subgroupId) {
                $q->where('subgroup_id', $subgroupId)
                    ->orWhereNull('subgroup_id');
            });
        } else {
            // For whole group: match any assignment (subgroup or null)
            // No additional filter needed
        }

        $assignedTeacherIds = $teacherQuery->pluck('teacher_user_id')->unique()->toArray();

        if (empty($assignedTeacherIds)) {
            return [];
        }

        // Get occupied teacher IDs at this time
        if ($versionIdsToCheck === [-1]) {
            $occupiedTeacherIds = [];
        } else {
            $occupiedTeacherIds = ScheduleItem::whereIn('version_id', $versionIdsToCheck)
                ->where('date', $dateStr)
                ->where('time_slot_id', $timeSlotId)
                ->whereIn('teacher_user_id', $assignedTeacherIds)
                ->pluck('teacher_user_id')
                ->toArray();
        }

        // Get available teachers
        $availableTeacherIds = array_diff($assignedTeacherIds, $occupiedTeacherIds);

        if (empty($availableTeacherIds)) {
            return [];
        }

        // Get teacher details
        $teachers = DB::table('users')
            ->whereIn('id', $availableTeacherIds)
            ->where('status', 'active')
            ->select('id', 'fio', 'email')
            ->get()
            ->map(function ($teacher) {
                return [
                    'teacher_user_id' => $teacher->id,
                    'fio' => $teacher->fio,
                    'email' => $teacher->email,
                ];
            })
            ->toArray();

        return $teachers;
    }
}

