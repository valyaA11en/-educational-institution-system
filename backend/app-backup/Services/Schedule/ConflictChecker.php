<?php

namespace App\Services\Schedule;

use App\Models\ScheduleItem;
use Carbon\CarbonImmutable;

class ConflictChecker
{
    public function checkConflicts(
        CarbonImmutable $date,
        int $timeSlotId,
        int $roomId,
        int $teacherUserId,
        int $groupId,
        ?int $subgroupId = null,
        ?int $excludeScheduleItemId = null
    ): array {
        $conflicts = [];

        // Room conflict
        $roomConflict = ScheduleItem::where('date', $date->format('Y-m-d'))
            ->where('time_slot_id', $timeSlotId)
            ->where('room_id', $roomId)
            ->when($excludeScheduleItemId, fn($q) => $q->where('id', '!=', $excludeScheduleItemId))
            ->with(['group', 'subject', 'teacher'])
            ->first();

        if ($roomConflict) {
            $conflicts[] = [
                'type' => 'room',
                'entity_id' => $roomId,
                'date' => $date->format('Y-m-d'),
                'time_slot_id' => $timeSlotId,
                'message' => "Кабинет занят: {$roomConflict->group->name} - {$roomConflict->subject->name} ({$roomConflict->teacher->fio})",
                'conflicting_item' => [
                    'id' => $roomConflict->id,
                    'group' => $roomConflict->group->name,
                    'subject' => $roomConflict->subject->name,
                    'teacher' => $roomConflict->teacher->fio,
                ],
            ];
        }

        // Teacher conflict
        $teacherConflict = ScheduleItem::where('date', $date->format('Y-m-d'))
            ->where('time_slot_id', $timeSlotId)
            ->where('teacher_user_id', $teacherUserId)
            ->when($excludeScheduleItemId, fn($q) => $q->where('id', '!=', $excludeScheduleItemId))
            ->with(['group', 'subject', 'room'])
            ->first();

        if ($teacherConflict) {
            $conflicts[] = [
                'type' => 'teacher',
                'entity_id' => $teacherUserId,
                'date' => $date->format('Y-m-d'),
                'time_slot_id' => $timeSlotId,
                'message' => "Преподаватель занят: {$teacherConflict->group->name} - {$teacherConflict->subject->name} (каб. {$teacherConflict->room->name})",
                'conflicting_item' => [
                    'id' => $teacherConflict->id,
                    'group' => $teacherConflict->group->name,
                    'subject' => $teacherConflict->subject->name,
                    'room' => $teacherConflict->room->name,
                ],
            ];
        }

        // Group conflict (same group, same time, different subgroup is OK)
        $groupConflict = ScheduleItem::where('date', $date->format('Y-m-d'))
            ->where('time_slot_id', $timeSlotId)
            ->where('group_id', $groupId)
            ->when($subgroupId === null, fn($q) => $q->whereNull('subgroup_id'))
            ->when($subgroupId !== null, fn($q) => $q->where(function ($q) use ($subgroupId) {
                $q->whereNull('subgroup_id')->orWhere('subgroup_id', $subgroupId);
            }))
            ->when($excludeScheduleItemId, fn($q) => $q->where('id', '!=', $excludeScheduleItemId))
            ->with(['subject', 'teacher', 'room'])
            ->first();

        if ($groupConflict) {
            $conflicts[] = [
                'type' => 'group',
                'entity_id' => $groupId,
                'date' => $date->format('Y-m-d'),
                'time_slot_id' => $timeSlotId,
                'message' => "Группа занята: {$groupConflict->subject->name} ({$groupConflict->teacher->fio}, каб. {$groupConflict->room->name})",
                'conflicting_item' => [
                    'id' => $groupConflict->id,
                    'subject' => $groupConflict->subject->name,
                    'teacher' => $groupConflict->teacher->fio,
                    'room' => $groupConflict->room->name,
                ],
            ];
        }

        return $conflicts;
    }
}








