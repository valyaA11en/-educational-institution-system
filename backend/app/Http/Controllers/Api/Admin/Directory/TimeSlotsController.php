<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TimeSlotsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $timeSlots = TimeSlot::forTenant($tenantId)->ordered()->get();
        return response()->json(['data' => $timeSlots]);
    }

    public function store(Request $request): JsonResponse
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

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $timeSlot = TimeSlot::forTenant($tenantId)->findOrFail($id);
        return response()->json(['data' => $timeSlot]);
    }

    public function update(Request $request, $id): JsonResponse
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

    public function destroy(Request $request, $id): JsonResponse
    {
        $timeSlot = TimeSlot::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $timeSlot->delete();
        return response()->json(['message' => 'Time slot deleted successfully']);
    }
}