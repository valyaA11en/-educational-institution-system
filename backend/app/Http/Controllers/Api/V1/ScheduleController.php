<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Schedule\Services\ScheduleConflictService;
use App\Domains\Schedule\Services\ScheduleSuggestService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\ScheduleItemCreateRequest;
use App\Http\Requests\Schedule\SuggestRoomRequest;
use App\Http\Requests\Schedule\SuggestTeacherRequest;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Services\AuditService;
use App\Services\Outbox\OutboxService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleConflictService $conflictService,
        private ScheduleSuggestService $suggestService,
        private OutboxService $outboxService
    ) {}

    public function versions(Request $request): JsonResponse
    {
        $versions = ScheduleVersion::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($versions);
    }

    public function createVersion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'status' => ['sometimes', 'in:draft,published,archived'],
        ]);

        $version = ScheduleVersion::create([
            'term_id' => $validated['term_id'],
            'status' => $validated['status'] ?? 'draft',
            'created_by' => auth()->id(),
        ]);

        AuditService::log('schedule.version.created', 'schedule_version', $version->id, null, $version->toArray());

        return response()->json($version->load('creator'), 201);
    }

    public function items(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduleItem::class);

        $query = ScheduleItem::with(['group', 'subgroup', 'subject', 'teacher', 'room', 'version']);

        if ($versionId = $request->query('version_id')) {
            $query->where('version_id', $versionId);
        }

        if ($date = $request->query('date')) {
            $query->where('date', $date);
        }

        if ($groupId = $request->query('group_id')) {
            $query->where('group_id', $groupId);
        }

        if ($teacherId = $request->query('teacher_id')) {
            $query->where('teacher_user_id', $teacherId);
        }

        if ($roomId = $request->query('room_id')) {
            $query->where('room_id', $roomId);
        }

        $items = $query->orderBy('date')->orderBy('time_slot_id')->paginate($request->integer('per_page', 100));

        return response()->json($items);
    }

    public function createItem(ScheduleItemCreateRequest $request): JsonResponse
    {
        $this->authorize('create', ScheduleItem::class);

        if ($request->boolean('force')) {
            $this->authorize('forceOverride', ScheduleItem::class);
        }

        $dto = $request->toDTO();

        // Detect conflicts
        $conflicts = $this->conflictService->detectConflicts(
            $dto->date,
            $dto->timeSlotId,
            $dto->groupId,
            $dto->subgroupId,
            $dto->teacherUserId,
            $dto->roomId,
            $dto->versionId
        );

        if (!empty($conflicts) && !$dto->force) {
            return response()->json([
                'message' => 'Обнаружены конфликты в расписании',
                'conflicts' => $conflicts,
            ], 409);
        }

        if ($dto->force && empty($dto->overrideReason)) {
            return response()->json([
                'message' => 'При force=true обязателен override_reason',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $before = null;
            $item = ScheduleItem::create([
                'version_id' => $dto->versionId,
                'date' => $dto->date->format('Y-m-d'),
                'time_slot_id' => $dto->timeSlotId,
                'group_id' => $dto->groupId,
                'subgroup_id' => $dto->subgroupId,
                'subject_id' => $dto->subjectId,
                'teacher_user_id' => $dto->teacherUserId,
                'room_id' => $dto->roomId,
                'override_reason' => $dto->overrideReason,
                'created_by' => auth()->id(),
            ]);

            $after = $item->toArray();
            $after['conflicts'] = $conflicts;

            // Audit log
            $action = $dto->force ? 'schedule.force_override' : 'schedule.item.created';
            AuditService::log(
                $action,
                'schedule_item',
                $item->id,
                $before,
                $after,
                auth()->id(),
                $request->ip()
            );

            // Create outbox event
            $this->outboxService->record(
                eventType: 'schedule.changed',
                actorUserId: auth()->id(),
                entityType: 'schedule_item',
                entityId: $item->id,
                payload: [
                    'scheduleItemId' => $item->id,
                    'date' => $item->date->format('Y-m-d'),
                    'timeSlotId' => $item->time_slot_id,
                    'groupId' => $item->group_id,
                    'subgroupId' => $item->subgroup_id,
                    'teacherId' => $item->teacher_user_id,
                    'roomId' => $item->room_id,
                    'versionId' => $item->version_id,
                ],
                idempotencyKey: 'schedule_item_' . $item->id . '_' . Str::uuid()
            );

            DB::commit();

            return response()->json($item->load(['group', 'subgroup', 'subject', 'teacher', 'room', 'version']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function replacements(Request $request): JsonResponse
    {
        $this->authorize('manageReplacements', ScheduleItem::class);
        // TODO: получить замены в расписании
        return response()->json(['message' => 'Not implemented']);
    }

    public function createReplacement(Request $request): JsonResponse
    {
        $this->authorize('manageReplacements', ScheduleItem::class);
        // TODO: создать замену в расписании
        return response()->json(['message' => 'Not implemented']);
    }

    public function suggestRoom(SuggestRoomRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $date = CarbonImmutable::parse($validated['date']);
        $timeSlotId = (int) $validated['time_slot_id'];
        $versionId = isset($validated['version_id']) ? (int) $validated['version_id'] : null;
        $required = $validated['required'] ?? null;

        $rooms = $this->suggestService->suggestRooms($date, $timeSlotId, $versionId, $required);

        return response()->json([
            'data' => $rooms,
        ]);
    }

    public function suggestTeacher(SuggestTeacherRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $date = CarbonImmutable::parse($validated['date']);
        $timeSlotId = (int) $validated['time_slot_id'];
        $subjectId = (int) $validated['subject_id'];
        $groupId = (int) $validated['group_id'];
        $subgroupId = isset($validated['subgroup_id']) ? (int) $validated['subgroup_id'] : null;
        $versionId = isset($validated['version_id']) ? (int) $validated['version_id'] : null;

        $teachers = $this->suggestService->suggestTeachers(
            $date,
            $timeSlotId,
            $subjectId,
            $groupId,
            $subgroupId,
            $versionId
        );

        return response()->json([
            'data' => $teachers,
        ]);
    }
}
