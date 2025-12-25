<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\ScheduleItemCreateRequest;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Services\AuditService;
use App\Services\Schedule\ConflictChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function __construct(
        private ConflictChecker $conflictChecker
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
        $dto = $request->toDTO();

        // Check conflicts
        $conflicts = $this->conflictChecker->checkConflicts(
            $dto->date,
            $dto->timeSlotId,
            $dto->roomId,
            $dto->teacherUserId,
            $dto->groupId,
            $dto->subgroupId
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
            // Get or create draft version
            $version = ScheduleVersion::where('status', 'draft')
                ->where('term_id', $request->input('term_id')) // TODO: get term_id from request
                ->first();

            if (!$version) {
                $version = ScheduleVersion::create([
                    'term_id' => $request->input('term_id', 1), // TODO: get current term
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]);
            }

            $before = null;
            $item = ScheduleItem::create([
                'version_id' => $version->id,
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

            AuditService::log(
                'schedule.item.created',
                'schedule_item',
                $item->id,
                $before,
                $after,
                auth()->id(),
                $request->ip()
            );

            DB::commit();

            return response()->json($item->load(['group', 'subgroup', 'subject', 'teacher', 'room']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function replacements(Request $request): JsonResponse
    {
        // TODO: получить замены в расписании
        return response()->json(['message' => 'Not implemented']);
    }

    public function createReplacement(Request $request): JsonResponse
    {
        // TODO: создать замену в расписании
        return response()->json(['message' => 'Not implemented']);
    }

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'time_slot_id' => ['required', 'integer'],
            'group_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'teacher_user_id' => ['required', 'integer'],
        ]);

        // TODO: предложить варианты расписания (свободные кабинеты)
        return response()->json(['message' => 'Not implemented']);
    }
}
