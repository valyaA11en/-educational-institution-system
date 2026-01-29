<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Room;
use App\Models\Subgroup;
use App\Models\Subject;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class DirectoryController extends Controller
{
    // Groups
    public function groups(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $groups = Group::forTenant($tenantId)->get();
        return response()->json(['data' => $groups]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:groups,code',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group = Group::create([
            'name' => $request->name,
            'code' => $request->code,
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        return response()->json(['data' => $group], 201);
    }

    public function updateGroup(Request $request, $id): JsonResponse
    {
        $group = Group::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:groups,code,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group->update($request->only(['name', 'code']));
        return response()->json(['data' => $group]);
    }

    public function destroyGroup(Request $request, $id): JsonResponse
    {
        $group = Group::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $group->delete();
        return response()->json(['message' => 'Group deleted successfully']);
    }

    // Subgroups
    public function subgroups(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $query = Subgroup::forTenant($tenantId)->with('group');
        
        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        
        $subgroups = $query->get();
        return response()->json(['data' => $subgroups]);
    }

    public function storeSubgroup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:subgroups,code',
            'group_id' => 'required|exists:groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subgroup = Subgroup::create([
            'name' => $request->name,
            'code' => $request->code,
            'group_id' => $request->group_id,
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        return response()->json(['data' => $subgroup->load('group')], 201);
    }

    public function updateSubgroup(Request $request, $id): JsonResponse
    {
        $subgroup = Subgroup::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:subgroups,code,' . $id,
            'group_id' => 'sometimes|exists:groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subgroup->update($request->only(['name', 'code', 'group_id']));
        return response()->json(['data' => $subgroup->load('group')]);
    }

    public function destroySubgroup(Request $request, $id): JsonResponse
    {
        $subgroup = Subgroup::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $subgroup->delete();
        return response()->json(['message' => 'Subgroup deleted successfully']);
    }

    public function subjects(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $subjects = Subject::forTenant($tenantId)->get();
        return response()->json(['data' => $subjects]);
    }

    public function storeSubject(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:subjects,code',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subject = Subject::create([
            'name' => $request->name,
            'code' => $request->code,
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        return response()->json(['data' => $subject], 201);
    }

    public function updateSubject(Request $request, $id): JsonResponse
    {
        $subject = Subject::forTenant(Auth::user()->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:subjects,code,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subject->update($request->only(['name', 'code']));
        return response()->json(['data' => $subject]);
    }

    public function destroySubject(Request $request, $id): JsonResponse
    {
        $subject = Subject::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $subject->delete();
        return response()->json(['message' => 'Subject deleted successfully']);
    }

    // Rooms
    public function rooms(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $rooms = Room::forTenant($tenantId)->get();
        return response()->json(['data' => $rooms]);
    }

    public function storeRoom(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:rooms,code',
            'capacity' => 'nullable|integer|min:1',
            'room_type' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = Room::create([
            'name' => $request->name,
            'code' => $request->code,
            'capacity' => $request->capacity,
            'room_type' => $request->room_type,
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        return response()->json(['data' => $room], 201);
    }

    public function updateRoom(Request $request, $id): JsonResponse
    {
        $room = Room::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:rooms,code,' . $id,
            'capacity' => 'nullable|integer|min:1',
            'room_type' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room->update($request->only(['name', 'code', 'capacity', 'room_type']));
        return response()->json(['data' => $room]);
    }

    public function destroyRoom(Request $request, $id): JsonResponse
    {
        $room = Room::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $room->delete();
        return response()->json(['message' => 'Room deleted successfully']);
    }

    // Time Slots
    public function timeSlots(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $timeSlots = TimeSlot::forTenant($tenantId)->ordered()->get();
        return response()->json(['data' => $timeSlots]);
    }

    public function storeTimeSlot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'order' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $timeSlot = TimeSlot::create([
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'order' => $request->order ?? 1,
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        return response()->json(['data' => $timeSlot], 201);
    }

    public function updateTimeSlot(Request $request, $id): JsonResponse
    {
        $timeSlot = TimeSlot::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'order' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $timeSlot->update($request->only(['name', 'start_time', 'end_time', 'order']));
        return response()->json(['data' => $timeSlot]);
    }

    public function destroyTimeSlot(Request $request, $id): JsonResponse
    {
        $timeSlot = TimeSlot::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $timeSlot->delete();
        return response()->json(['message' => 'Time slot deleted successfully']);
    }
}