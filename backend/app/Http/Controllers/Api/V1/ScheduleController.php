<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScheduleVersion;
use App\Models\ScheduleItem;
use App\Models\ScheduleChangelog;
use App\Models\ScheduleReplacement;
use App\Models\Term;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    /**
     * Get list of schedule versions
     */
    public function versions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:draft,published,archived',
            'term_id' => 'nullable|integer|exists:terms,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = ScheduleVersion::with(['creator:id,fio', 'term:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('term_id')) {
            $query->where('term_id', $request->term_id);
        }

        $perPage = $request->get('per_page', 20);
        $versions = $query->paginate($perPage);

        return response()->json([
            'data' => $versions->items(),
            'current_page' => $versions->currentPage(),
            'per_page' => $versions->perPage(),
            'total' => $versions->total(),
            'last_page' => $versions->lastPage(),
        ]);
    }

    /**
     * Create a new schedule version
     */
    public function createVersion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'term_id' => 'required|integer|exists:terms,id',
            'status' => 'nullable|in:draft,published,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        try {
            DB::beginTransaction();

            // Get tenant_id from user or request
            $tenantId = $user->tenant_id ?? $request->tenant_id ?? null;

            $version = ScheduleVersion::create([
                'tenant_id' => $tenantId,
                'term_id' => $request->term_id,
                'status' => $request->status ?? 'draft',
                'created_by' => $user->id,
                'published_at' => $request->status === 'published' ? now() : null,
            ]);

            // Log the creation
            ScheduleChangelog::create([
                'version_id' => $version->id,
                'action' => 'create',
                'actor_user_id' => $user->id,
                'after_json' => $version->toArray(),
            ]);

            DB::commit();

            $version->load(['creator:id,fio', 'term:id,name']);

            return response()->json($version, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create version: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a schedule version
     */
    public function publishVersion(Request $request, $id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $version = ScheduleVersion::find($id);
        if (!$version) {
            return response()->json([
                'success' => false,
                'message' => 'Version not found'
            ], 404);
        }

        if ($version->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Version is already published'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Archive other published versions for the same term
            ScheduleVersion::where('term_id', $version->term_id)
                ->where('status', 'published')
                ->where('id', '!=', $version->id)
                ->update([
                    'status' => 'archived',
                ]);

            // Publish this version
            $version->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            // Log the publication
            ScheduleChangelog::create([
                'version_id' => $version->id,
                'action' => 'publish',
                'actor_user_id' => $user->id,
                'before_json' => ['status' => 'draft'],
                'after_json' => ['status' => 'published', 'published_at' => $version->published_at],
            ]);

            DB::commit();

            $version->load(['creator:id,fio', 'term:id,name']);

            return response()->json($version);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish version: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archive a schedule version
     */
    public function archiveVersion(Request $request, $id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $version = ScheduleVersion::find($id);
        if (!$version) {
            return response()->json([
                'success' => false,
                'message' => 'Version not found'
            ], 404);
        }

        if ($version->isArchived()) {
            return response()->json([
                'success' => false,
                'message' => 'Version is already archived'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $oldStatus = $version->status;
            $version->update([
                'status' => 'archived',
            ]);

            // Log the archivation
            ScheduleChangelog::create([
                'version_id' => $version->id,
                'action' => 'archive',
                'actor_user_id' => $user->id,
                'before_json' => ['status' => $oldStatus],
                'after_json' => ['status' => 'archived'],
            ]);

            DB::commit();

            $version->load(['creator:id,fio', 'term:id,name']);

            return response()->json($version);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive version: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get changelog for a version
     */
    public function changelog(Request $request, $id): JsonResponse
    {
        $version = ScheduleVersion::find($id);
        if (!$version) {
            return response()->json([
                'success' => false,
                'message' => 'Version not found'
            ], 404);
        }

        $query = ScheduleChangelog::with([
            'actor:id,fio',
            'scheduleItem:id,date,group_id,subject_id',
            'scheduleItem.group:id,name',
            'scheduleItem.subject:id,name',
        ])
            ->where('version_id', $id)
            ->orderBy('created_at', 'desc');

        if ($request->has('item_id')) {
            $query->where('schedule_item_id', $request->item_id);
        }

        $changelog = $query->get();

        return response()->json([
            'data' => $changelog,
        ]);
    }

    /**
     * Compare two schedule versions
     */
    public function compareVersions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version1_id' => 'required|integer|exists:schedule_versions,id',
            'version2_id' => 'required|integer|exists:schedule_versions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $version1 = ScheduleVersion::with('items')->find($request->version1_id);
        $version2 = ScheduleVersion::with('items')->find($request->version2_id);

        if (!$version1 || !$version2) {
            return response()->json([
                'success' => false,
                'message' => 'One or both versions not found'
            ], 404);
        }

        // Get items from both versions
        $items1 = $version1->items->keyBy(function ($item) {
            return "{$item->date}_{$item->time_slot_id}_{$item->group_id}_{$item->subject_id}";
        });

        $items2 = $version2->items->keyBy(function ($item) {
            return "{$item->date}_{$item->time_slot_id}_{$item->group_id}_{$item->subject_id}";
        });

        // Find added items (in version2 but not in version1)
        $added = $items2->diffKeys($items1)->values();

        // Find removed items (in version1 but not in version2)
        $removed = $items1->diffKeys($items2)->values();

        // Find modified items
        $modified = [];
        foreach ($items1 as $key => $item1) {
            if ($items2->has($key)) {
                $item2 = $items2->get($key);
                if ($item1->toArray() !== $item2->toArray()) {
                    $modified[] = [
                        'item' => $item2->load(['group:id,name', 'subject:id,name', 'teacher:id,fio', 'room:id,name']),
                        'before' => $item1->toArray(),
                        'after' => $item2->toArray(),
                    ];
                }
            }
        }

        return response()->json([
            'added' => $added->load(['group:id,name', 'subject:id,name', 'teacher:id,fio', 'room:id,name'])->toArray(),
            'removed' => $removed->load(['group:id,name', 'subject:id,name', 'teacher:id,fio', 'room:id,name'])->toArray(),
            'modified' => $modified,
        ]);
    }

    /**
     * Get changes for a version
     */
    public function changes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'versionId' => 'required|integer|exists:schedule_versions,id',
            'dateFrom' => 'nullable|date',
            'dateTo' => 'nullable|date|after_or_equal:dateFrom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = ScheduleChangelog::with([
            'actor:id,fio',
            'scheduleItem:id,date,group_id,subject_id',
            'scheduleItem.group:id,name',
            'scheduleItem.subject:id,name',
        ])
            ->where('version_id', $request->versionId)
            ->orderBy('created_at', 'desc');

        if ($request->has('dateFrom')) {
            $query->whereDate('created_at', '>=', $request->dateFrom);
        }

        if ($request->has('dateTo')) {
            $query->whereDate('created_at', '<=', $request->dateTo);
        }

        $changes = $query->get();

        return response()->json([
            'data' => $changes,
        ]);
    }

    /**
     * Get diff between two versions
     */
    public function diff(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fromVersionId' => 'required|integer|exists:schedule_versions,id',
            'toVersionId' => 'required|integer|exists:schedule_versions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Use compareVersions logic
        return $this->compareVersions(new Request([
            'version1_id' => $request->fromVersionId,
            'version2_id' => $request->toVersionId,
        ]));
    }

    /**
     * Get schedule items
     */
    public function items(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version_id' => 'nullable|integer|exists:schedule_versions,id',
            'date' => 'nullable|date',
            'group_id' => 'nullable|integer|exists:groups,id',
            'teacher_id' => 'nullable|integer|exists:users,id',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = ScheduleItem::with([
            'group:id,name',
            'subject:id,name',
            'teacher:id,fio',
            'room:id,name',
            'timeSlot:id,start_time,end_time',
        ])
            ->orderBy('date')
            ->orderBy('time_slot_id');

        if ($request->has('version_id')) {
            $query->where('version_id', $request->version_id);
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->has('teacher_id')) {
            $query->where('teacher_user_id', $request->teacher_id);
        }

        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        $perPage = $request->get('per_page', 50);
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ]);
    }

    /**
     * Create a new schedule item
     */
    public function createItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version_id' => 'required|integer|exists:schedule_versions,id',
            'date' => 'required|date',
            'time_slot_id' => 'required|integer|exists:time_slots,id',
            'group_id' => 'required|integer|exists:groups,id',
            'subgroup_id' => 'nullable|integer|exists:subgroups,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'teacher_user_id' => 'required|integer|exists:users,id',
            'room_id' => 'required|integer|exists:rooms,id',
            'override_reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $version = ScheduleVersion::find($request->version_id);
        if (!$version) {
            return response()->json([
                'success' => false,
                'message' => 'Version not found'
            ], 404);
        }

        // Check if version is not archived or published
        if ($version->isArchived() || $version->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add items to archived or published version'
            ], 400);
        }

        // Check for conflicts (optional - can be disabled with force flag)
        if (!$request->has('force') || !$request->force) {
            $conflicts = $this->checkConflicts(
                $request->version_id,
                $request->date,
                $request->time_slot_id,
                $request->group_id,
                $request->teacher_user_id,
                $request->room_id
            );

            if (!empty($conflicts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule conflicts detected',
                    'conflicts' => $conflicts
                ], 409);
            }
        }

        try {
            DB::beginTransaction();

            // Get tenant_id from version or user
            $tenantId = $version->tenant_id ?? $user->tenant_id ?? null;

            $item = ScheduleItem::create([
                'tenant_id' => $tenantId,
                'version_id' => $request->version_id,
                'date' => $request->date,
                'time_slot_id' => $request->time_slot_id,
                'group_id' => $request->group_id,
                'subgroup_id' => $request->subgroup_id,
                'subject_id' => $request->subject_id,
                'teacher_user_id' => $request->teacher_user_id,
                'room_id' => $request->room_id,
                'override_reason' => $request->override_reason,
                'created_by' => $user->id,
            ]);

            // Log the creation
            ScheduleChangelog::create([
                'version_id' => $version->id,
                'action' => 'create',
                'schedule_item_id' => $item->id,
                'actor_user_id' => $user->id,
                'after_json' => $item->toArray(),
            ]);

            DB::commit();

            $item->load([
                'group:id,name',
                'subject:id,name',
                'teacher:id,fio',
                'room:id,name',
                'timeSlot:id,start_time,end_time',
            ]);

            return response()->json($item, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a schedule item
     */
    public function updateItem(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'time_slot_id' => 'nullable|integer|exists:time_slots,id',
            'group_id' => 'nullable|integer|exists:groups,id',
            'subgroup_id' => 'nullable|integer|exists:subgroups,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'teacher_user_id' => 'nullable|integer|exists:users,id',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'override_reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $item = ScheduleItem::with('version')->find($id);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        // Check if version is not archived or published
        if ($item->version->isArchived() || $item->version->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify items in archived or published version'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $beforeData = $item->toArray();

            // Update only provided fields
            $updateData = [];
            $fields = ['date', 'time_slot_id', 'group_id', 'subgroup_id', 'subject_id', 'teacher_user_id', 'room_id', 'override_reason'];
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }

            // Check for conflicts if updating critical fields
            if (!$request->has('force') || !$request->force) {
                $checkDate = $updateData['date'] ?? $item->date;
                $checkTimeSlot = $updateData['time_slot_id'] ?? $item->time_slot_id;
                $checkGroup = $updateData['group_id'] ?? $item->group_id;
                $checkTeacher = $updateData['teacher_user_id'] ?? $item->teacher_user_id;
                $checkRoom = $updateData['room_id'] ?? $item->room_id;

                $conflicts = $this->checkConflicts(
                    $item->version_id,
                    $checkDate,
                    $checkTimeSlot,
                    $checkGroup,
                    $checkTeacher,
                    $checkRoom,
                    $item->id
                );

                if (!empty($conflicts)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Schedule conflicts detected',
                        'conflicts' => $conflicts
                    ], 409);
                }
            }

            $item->update($updateData);

            // Log the update
            ScheduleChangelog::create([
                'version_id' => $item->version_id,
                'action' => 'update',
                'schedule_item_id' => $item->id,
                'actor_user_id' => $user->id,
                'before_json' => $beforeData,
                'after_json' => $item->fresh()->toArray(),
            ]);

            DB::commit();

            $item->load([
                'group:id,name',
                'subject:id,name',
                'teacher:id,fio',
                'room:id,name',
                'timeSlot:id,start_time,end_time',
            ]);

            return response()->json($item);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a schedule item
     */
    public function deleteItem(Request $request, $id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $item = ScheduleItem::with('version')->find($id);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        // Check if version is not archived or published
        if ($item->version->isArchived() || $item->version->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete items from archived or published version'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $beforeData = $item->toArray();
            $versionId = $item->version_id;

            // Log the deletion before deleting
            ScheduleChangelog::create([
                'version_id' => $versionId,
                'action' => 'delete',
                'schedule_item_id' => $item->id,
                'actor_user_id' => $user->id,
                'before_json' => $beforeData,
                'after_json' => null,
            ]);

            $item->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for conflicts when creating/updating a schedule item
     */
    private function checkConflicts($versionId, $date, $timeSlotId, $groupId, $teacherUserId, $roomId, $excludeItemId = null): array
    {
        $conflicts = [];

        // Check for teacher conflicts (same teacher, same time, same date)
        $teacherConflict = ScheduleItem::where('version_id', $versionId)
            ->where('date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->where('teacher_user_id', $teacherUserId)
            ->when($excludeItemId, function ($query) use ($excludeItemId) {
                return $query->where('id', '!=', $excludeItemId);
            })
            ->first();

        if ($teacherConflict) {
            $conflicts[] = [
                'type' => 'teacher',
                'message' => 'Преподаватель уже занят в это время',
                'item' => $teacherConflict->load(['group:id,name', 'subject:id,name']),
            ];
        }

        // Check for room conflicts (same room, same time, same date)
        $roomConflict = ScheduleItem::where('version_id', $versionId)
            ->where('date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->where('room_id', $roomId)
            ->when($excludeItemId, function ($query) use ($excludeItemId) {
                return $query->where('id', '!=', $excludeItemId);
            })
            ->first();

        if ($roomConflict) {
            $conflicts[] = [
                'type' => 'room',
                'message' => 'Кабинет уже занят в это время',
                'item' => $roomConflict->load(['group:id,name', 'subject:id,name']),
            ];
        }

        // Check for group conflicts (same group, same time, same date)
        $groupConflict = ScheduleItem::where('version_id', $versionId)
            ->where('date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->where('group_id', $groupId)
            ->when($excludeItemId, function ($query) use ($excludeItemId) {
                return $query->where('id', '!=', $excludeItemId);
            })
            ->first();

        if ($groupConflict) {
            $conflicts[] = [
                'type' => 'group',
                'message' => 'Группа уже имеет занятие в это время',
                'item' => $groupConflict->load(['subject:id,name', 'teacher:id,fio']),
            ];
        }

        return $conflicts;
    }

    /**
     * Get schedule replacements
     */
    public function replacements(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_item_id' => 'nullable|integer|exists:schedule_items,id',
            'date' => 'nullable|date',
            'status' => 'nullable|in:draft,approved,rejected,applied',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = ScheduleReplacement::with([
            'scheduleItem:id,date,group_id,subject_id,teacher_user_id,room_id',
            'scheduleItem.group:id,name',
            'scheduleItem.subject:id,name',
            'scheduleItem.teacher:id,fio',
            'scheduleItem.room:id,name',
            'newTeacher:id,fio',
            'newRoom:id,name',
            'approver:id,fio',
        ])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->has('schedule_item_id')) {
            $query->where('schedule_item_id', $request->schedule_item_id);
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 20);
        $replacements = $query->paginate($perPage);

        return response()->json([
            'data' => $replacements->items(),
            'current_page' => $replacements->currentPage(),
            'per_page' => $replacements->perPage(),
            'total' => $replacements->total(),
            'last_page' => $replacements->lastPage(),
        ]);
    }

    /**
     * Create schedule replacement
     */
    public function createReplacement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_item_id' => 'required|integer|exists:schedule_items,id',
            'date' => 'required|date',
            'new_teacher_user_id' => 'nullable|integer|exists:users,id',
            'new_room_id' => 'nullable|integer|exists:rooms,id',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $scheduleItem = ScheduleItem::with('version')->find($request->schedule_item_id);
        if (!$scheduleItem) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule item not found'
            ], 404);
        }

        // Check if at least one replacement field is provided
        if (!$request->has('new_teacher_user_id') && !$request->has('new_room_id')) {
            return response()->json([
                'success' => false,
                'message' => 'At least one replacement field (new_teacher_user_id or new_room_id) must be provided'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tenantId = $scheduleItem->tenant_id ?? $user->tenant_id ?? null;

            $replacement = ScheduleReplacement::create([
                'tenant_id' => $tenantId,
                'schedule_item_id' => $request->schedule_item_id,
                'date' => $request->date,
                'new_teacher_user_id' => $request->new_teacher_user_id,
                'new_room_id' => $request->new_room_id,
                'reason' => $request->reason,
                'status' => 'draft',
            ]);

            DB::commit();

            $replacement->load([
                'scheduleItem:id,date,group_id,subject_id,teacher_user_id,room_id',
                'scheduleItem.group:id,name',
                'scheduleItem.subject:id,name',
                'scheduleItem.teacher:id,fio',
                'scheduleItem.room:id,name',
                'newTeacher:id,fio',
                'newRoom:id,name',
            ]);

            return response()->json($replacement, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create replacement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve schedule replacement
     */
    public function approveReplacement(Request $request, $id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $replacement = ScheduleReplacement::find($id);
        if (!$replacement) {
            return response()->json([
                'success' => false,
                'message' => 'Replacement not found'
            ], 404);
        }

        if ($replacement->isApproved() || $replacement->isApplied()) {
            return response()->json([
                'success' => false,
                'message' => 'Replacement is already approved or applied'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $replacement->update([
                'status' => 'approved',
                'approved_by' => $user->id,
            ]);

            DB::commit();

            $replacement->load([
                'scheduleItem',
                'newTeacher:id,fio',
                'newRoom:id,name',
                'approver:id,fio',
            ]);

            return response()->json($replacement);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve replacement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply schedule replacement (actually modify the schedule item)
     */
    public function applyReplacement(Request $request, $id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $replacement = ScheduleReplacement::with('scheduleItem.version')->find($id);
        if (!$replacement) {
            return response()->json([
                'success' => false,
                'message' => 'Replacement not found'
            ], 404);
        }

        if (!$replacement->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Replacement must be approved before applying'
            ], 400);
        }

        if ($replacement->isApplied()) {
            return response()->json([
                'success' => false,
                'message' => 'Replacement is already applied'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $scheduleItem = $replacement->scheduleItem;
            $updateData = [];

            if ($replacement->new_teacher_user_id) {
                $updateData['teacher_user_id'] = $replacement->new_teacher_user_id;
            }

            if ($replacement->new_room_id) {
                $updateData['room_id'] = $replacement->new_room_id;
            }

            if (!empty($updateData)) {
                $beforeData = $scheduleItem->toArray();
                $scheduleItem->update($updateData);

                // Log the change
                ScheduleChangelog::create([
                    'version_id' => $scheduleItem->version_id,
                    'action' => 'force_override',
                    'schedule_item_id' => $scheduleItem->id,
                    'actor_user_id' => $user->id,
                    'before_json' => $beforeData,
                    'after_json' => $scheduleItem->fresh()->toArray(),
                ]);
            }

            $replacement->update([
                'status' => 'applied',
            ]);

            DB::commit();

            $replacement->load([
                'scheduleItem',
                'newTeacher:id,fio',
                'newRoom:id,name',
                'approver:id,fio',
            ]);

            return response()->json($replacement);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply replacement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suggest room for schedule item
     */
    public function suggestRoom(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version_id' => 'required|integer|exists:schedule_versions,id',
            'date' => 'required|date',
            'time_slot_id' => 'required|integer|exists:time_slots,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find available rooms (not occupied at this time)
        $occupiedRoomIds = ScheduleItem::where('version_id', $request->version_id)
            ->where('date', $request->date)
            ->where('time_slot_id', $request->time_slot_id)
            ->pluck('room_id')
            ->toArray();

        $availableRooms = Room::whereNotIn('id', $occupiedRoomIds)
            ->when($request->has('subject_id'), function ($query) use ($request) {
                // If subject is provided, prefer rooms suitable for this subject
                // This is a placeholder - implement actual room-subject matching logic
                return $query;
            })
            ->limit(10)
            ->get(['id', 'name', 'capacity']);

        return response()->json([
            'data' => $availableRooms,
        ]);
    }

    /**
     * Suggest teacher for schedule item
     */
    public function suggestTeacher(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version_id' => 'required|integer|exists:schedule_versions,id',
            'date' => 'required|date',
            'time_slot_id' => 'required|integer|exists:time_slots,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'group_id' => 'nullable|integer|exists:groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find teachers who teach this subject and are available at this time
        $occupiedTeacherIds = ScheduleItem::where('version_id', $request->version_id)
            ->where('date', $request->date)
            ->where('time_slot_id', $request->time_slot_id)
            ->pluck('teacher_user_id')
            ->toArray();

        // Get teachers who teach this subject
        // This is a placeholder - implement actual teacher-subject assignment logic
        // For now, return all available teachers
        $availableTeachers = User::whereNotIn('id', $occupiedTeacherIds)
            ->where('status', 'active')
            ->limit(10)
            ->get(['id', 'fio', 'email']);

        return response()->json([
            'data' => $availableTeachers,
        ]);
    }
}
