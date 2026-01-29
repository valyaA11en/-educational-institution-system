<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Subgroup;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    /**
     * GROUPS
     */
    public function groups(Request $request): JsonResponse
    {
        $query = Group::query();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $groups = $query->orderBy('name')->paginate($request->integer('per_page', 50));

        return response()->json($groups);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $this->authorize('directory.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:groups,code'],
        ]);

        $group = Group::create($validated);

        return response()->json($group, Response::HTTP_CREATED);
    }

    public function updateGroup(Request $request, int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $group = Group::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'unique:groups,code,' . $group->id],
        ]);

        $group->fill($validated);
        $group->save();

        return response()->json($group);
    }

    public function destroyGroup(int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $group = Group::findOrFail($id);
        $group->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * SUBGROUPS
     */
    public function subgroups(Request $request): JsonResponse
    {
        $query = Subgroup::query()->with('group');

        if ($groupId = $request->query('group_id')) {
            $query->where('group_id', $groupId);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $subgroups = $query->orderBy('name')->paginate($request->integer('per_page', 50));

        return response()->json($subgroups);
    }

    public function storeSubgroup(Request $request): JsonResponse
    {
        $this->authorize('directory.manage');

        $validated = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:subgroups,code'],
        ]);

        $subgroup = Subgroup::create($validated);

        return response()->json($subgroup->load('group'), Response::HTTP_CREATED);
    }

    public function updateSubgroup(Request $request, int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $subgroup = Subgroup::findOrFail($id);

        $validated = $request->validate([
            'group_id' => ['sometimes', 'required', 'integer', 'exists:groups,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'unique:subgroups,code,' . $subgroup->id],
        ]);

        $subgroup->fill($validated);
        $subgroup->save();

        return response()->json($subgroup->load('group'));
    }

    public function destroySubgroup(int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $subgroup = Subgroup::findOrFail($id);
        $subgroup->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * SUBJECTS
     */
    public function subjects(Request $request): JsonResponse
    {
        $query = Subject::query();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $subjects = $query->orderBy('name')->paginate($request->integer('per_page', 50));

        return response()->json($subjects);
    }

    public function storeSubject(Request $request): JsonResponse
    {
        $this->authorize('directory.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:subjects,code'],
        ]);

        $subject = Subject::create($validated);

        return response()->json($subject, Response::HTTP_CREATED);
    }

    public function updateSubject(Request $request, int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'unique:subjects,code,' . $subject->id],
        ]);

        $subject->fill($validated);
        $subject->save();

        return response()->json($subject);
    }

    public function destroySubject(int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * ROOMS
     */
    public function rooms(Request $request): JsonResponse
    {
        $query = Room::query();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $rooms = $query->orderBy('code')->paginate($request->integer('per_page', 50));

        return response()->json($rooms);
    }

    public function storeRoom(Request $request): JsonResponse
    {
        $this->authorize('directory.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:rooms,code'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'attributes' => ['nullable', 'array'],
        ]);

        $room = Room::create($validated);

        return response()->json($room, Response::HTTP_CREATED);
    }

    public function updateRoom(Request $request, int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'unique:rooms,code,' . $room->id],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'attributes' => ['sometimes', 'nullable', 'array'],
        ]);

        $room->fill($validated);
        $room->save();

        return response()->json($room);
    }

    public function destroyRoom(int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * TIME SLOTS
     */
    public function timeSlots(Request $request): JsonResponse
    {
        $timeSlots = TimeSlot::query()
            ->orderBy('order')
            ->orderBy('start_time')
            ->get();

        return response()->json($timeSlots);
    }

    public function storeTimeSlot(Request $request): JsonResponse
    {
        $this->authorize('directory.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'order' => ['required', 'integer', 'min:1'],
        ]);

        $timeSlot = TimeSlot::create($validated);

        return response()->json($timeSlot, Response::HTTP_CREATED);
    }

    public function updateTimeSlot(Request $request, int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $timeSlot = TimeSlot::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'order' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $timeSlot->fill($validated);
        $timeSlot->save();

        return response()->json($timeSlot);
    }

    public function destroyTimeSlot(int $id): JsonResponse
    {
        $this->authorize('directory.manage');

        $timeSlot = TimeSlot::findOrFail($id);
        $timeSlot->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

