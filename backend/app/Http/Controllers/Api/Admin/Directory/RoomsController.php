<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class RoomsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $rooms = Room::forTenant($tenantId)->get();
        return response()->json(['data' => $rooms]);
    }

    public function store(Request $request): JsonResponse
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

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $room = Room::forTenant($tenantId)->findOrFail($id);
        return response()->json(['data' => $room]);
    }

    public function update(Request $request, $id): JsonResponse
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

    public function destroy(Request $request, $id): JsonResponse
    {
        $room = Room::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $room->delete();
        return response()->json(['message' => 'Room deleted successfully']);
    }
}