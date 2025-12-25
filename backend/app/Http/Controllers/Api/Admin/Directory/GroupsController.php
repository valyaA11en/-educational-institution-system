<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Directory\StoreGroupRequest;
use App\Http\Requests\Admin\Directory\UpdateGroupRequest;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GroupsController extends Controller
{
    public function index(Request $request): JsonResponse
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

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $group = Group::create($request->validated());

        return response()->json($group, Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        return response()->json($group);
    }

    public function update(UpdateGroupRequest $request, int $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $group->fill($request->validated());
        $group->save();

        return response()->json($group);
    }

    public function destroy(int $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $group->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

