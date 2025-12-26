<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Directory\StoreRoomRequest;
use App\Http\Requests\Admin\Directory\UpdateRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoomsController extends Controller
{
    public function index(Request $request): JsonResponse
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

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return response()->json($room, Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        return response()->json($room);
    }

    public function update(UpdateRoomRequest $request, int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->fill($request->validated());
        $room->save();

        return response()->json($room);
    }

    public function destroy(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}


