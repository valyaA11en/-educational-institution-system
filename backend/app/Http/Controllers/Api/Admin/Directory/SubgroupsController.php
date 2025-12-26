<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Directory\StoreSubgroupRequest;
use App\Http\Requests\Admin\Directory\UpdateSubgroupRequest;
use App\Models\Subgroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubgroupsController extends Controller
{
    public function index(Request $request): JsonResponse
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

    public function store(StoreSubgroupRequest $request): JsonResponse
    {
        $subgroup = Subgroup::create($request->validated());

        return response()->json($subgroup->load('group'), Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $subgroup = Subgroup::with('group')->findOrFail($id);

        return response()->json($subgroup);
    }

    public function update(UpdateSubgroupRequest $request, int $id): JsonResponse
    {
        $subgroup = Subgroup::findOrFail($id);
        $subgroup->fill($request->validated());
        $subgroup->save();

        return response()->json($subgroup->load('group'));
    }

    public function destroy(int $id): JsonResponse
    {
        $subgroup = Subgroup::findOrFail($id);
        $subgroup->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}


