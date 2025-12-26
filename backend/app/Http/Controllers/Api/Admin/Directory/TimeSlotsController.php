<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Directory\StoreTimeSlotRequest;
use App\Http\Requests\Admin\Directory\UpdateTimeSlotRequest;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimeSlotsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TimeSlot::query();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%");
            });
        }

        $timeSlots = $query
            ->orderBy('order')
            ->orderBy('start_time')
            ->paginate($request->integer('per_page', 50));

        return response()->json($timeSlots);
    }

    public function store(StoreTimeSlotRequest $request): JsonResponse
    {
        $timeSlot = TimeSlot::create($request->validated());

        return response()->json($timeSlot, Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $timeSlot = TimeSlot::findOrFail($id);

        return response()->json($timeSlot);
    }

    public function update(UpdateTimeSlotRequest $request, int $id): JsonResponse
    {
        $timeSlot = TimeSlot::findOrFail($id);
        $timeSlot->fill($request->validated());
        $timeSlot->save();

        return response()->json($timeSlot);
    }

    public function destroy(int $id): JsonResponse
    {
        $timeSlot = TimeSlot::findOrFail($id);
        $timeSlot->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}


