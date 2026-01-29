<?php

namespace App\Domains\Schedule\Services;

use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use Carbon\CarbonImmutable;

class ScheduleConflictService
{
    /**
     * Detect conflicts for a schedule item
     *
     * @param CarbonImmutable $date
     * @param int $timeSlotId
     * @param int $groupId
     * @param int|null $subgroupId
     * @param int $teacherUserId
     * @param int $roomId
     * @param int $versionId
     * @return array Array of conflicts
     */
    public function detectConflicts(
        CarbonImmutable $date,
        int $timeSlotId,
        int $groupId,
        ?int $subgroupId,
        int $teacherUserId,
        int $roomId,
        int $versionId
    ): array {
        $conflicts = [];

        $version = ScheduleVersion::findOrFail($versionId);
        $dateStr = $date->format('Y-m-d');

        // Get version IDs to check:
        // 1. Current version (if draft, check itself)
        // 2. Published version of the same term (if creating in draft)
        $versionIdsToCheck = [$versionId];

        if ($version->status === 'draft') {
            $publishedVersion = ScheduleVersion::where('term_id', $version->term_id)
                ->where('status', 'published')
                ->first();

            if ($publishedVersion) {
                $versionIdsToCheck[] = $publishedVersion->id;
            }
        }

        // 1. Room conflict
        $roomConflict = ScheduleItem::whereIn('version_id', $versionIdsToCheck)
            ->where('date', $dateStr)
            ->where('time_slot_id', $timeSlotId)
            ->where('room_id', $roomId)
            ->with(['group', 'subject', 'teacher', 'version'])
            ->first();

        if ($roomConflict) {
            $conflicts[] = [
                'type' => 'room',
                'entityId' => $roomId,
                'message' => "Кабинет занят: {$roomConflict->group->name} - {$roomConflict->subject->name} ({$roomConflict->teacher->fio})",
                'conflictingItemId' => $roomConflict->id,
                'details' => [
                    'room_id' => $roomConflict->room_id,
                    'group' => $roomConflict->group->name,
                    'subject' => $roomConflict->subject->name,
                    'teacher' => $roomConflict->teacher->fio,
                    'version_id' => $roomConflict->version_id,
                    'version_status' => $roomConflict->version->status,
                ],
            ];
        }

        // 2. Teacher conflict
        $teacherConflict = ScheduleItem::whereIn('version_id', $versionIdsToCheck)
            ->where('date', $dateStr)
            ->where('time_slot_id', $timeSlotId)
            ->where('teacher_user_id', $teacherUserId)
            ->with(['group', 'subject', 'room', 'version'])
            ->first();

        if ($teacherConflict) {
            $conflicts[] = [
                'type' => 'teacher',
                'entityId' => $teacherUserId,
                'message' => "Преподаватель занят: {$teacherConflict->group->name} - {$teacherConflict->subject->name} (каб. {$teacherConflict->room->name})",
                'conflictingItemId' => $teacherConflict->id,
                'details' => [
                    'teacher_user_id' => $teacherConflict->teacher_user_id,
                    'group' => $teacherConflict->group->name,
                    'subject' => $teacherConflict->subject->name,
                    'room' => $teacherConflict->room->name,
                    'version_id' => $teacherConflict->version_id,
                    'version_status' => $teacherConflict->version->status,
                ],
            ];
        }

        // 3. Group conflict
        // Logic:
        // - If creating for subgroup: conflict if there's an item for the whole group (subgroup_id is null)
        // - If creating for whole group: conflict if there's any item for any subgroup of this group
        $groupConflictQuery = ScheduleItem::whereIn('version_id', $versionIdsToCheck)
            ->where('date', $dateStr)
            ->where('time_slot_id', $timeSlotId)
            ->where('group_id', $groupId);

        if ($subgroupId === null) {
            // Creating for whole group: conflict if any subgroup has an item
            $groupConflictQuery->whereNotNull('subgroup_id');
        } else {
            // Creating for subgroup: conflict if whole group has an item (subgroup_id is null)
            $groupConflictQuery->whereNull('subgroup_id');
        }

        $groupConflict = $groupConflictQuery
            ->with(['subject', 'teacher', 'room', 'subgroup', 'version'])
            ->first();

        if ($groupConflict) {
            $conflictType = $subgroupId === null ? 'whole group' : 'subgroup';
            $existingType = $groupConflict->subgroup_id === null ? 'whole group' : "subgroup {$groupConflict->subgroup->name}";

            $conflicts[] = [
                'type' => 'group',
                'entityId' => $groupId,
                'message' => "Группа занята ({$existingType}): {$groupConflict->subject->name} ({$groupConflict->teacher->fio}, каб. {$groupConflict->room->name})",
                'conflictingItemId' => $groupConflict->id,
                'details' => [
                    'group_id' => $groupConflict->group_id,
                    'subgroup_id' => $groupConflict->subgroup_id,
                    'subject' => $groupConflict->subject->name,
                    'teacher' => $groupConflict->teacher->fio,
                    'room' => $groupConflict->room->name,
                    'version_id' => $groupConflict->version_id,
                    'version_status' => $groupConflict->version->status,
                ],
            ];
        }

        return $conflicts;
    }
}

