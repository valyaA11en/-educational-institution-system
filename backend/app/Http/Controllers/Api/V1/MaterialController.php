<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Material::class);

        $query = Material::with(['subject', 'creator', 'targets']);

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($groupId = $request->query('group_id')) {
            $query->whereHas('targets', function ($q) use ($groupId) {
                $q->where('group_id', $groupId);
            });
        }

        // VisibleToUserScope is applied automatically via global scope
        $materials = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($materials);
    }

    public function store(Request $request): JsonResponse
    {
        $subjectId = $request->input('subject_id');
        $groupId = $request->input('group_id');
        $this->authorize('create', [Material::class, $subjectId, $groupId]);
        // TODO: создать материал
        return response()->json(['message' => 'Not implemented']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // VisibleToUserScope is applied automatically - will return 404 if not visible
        $material = Material::with(['subject', 'creator', 'targets'])->findOrFail($id);
        $this->authorize('view', $material);

        return response()->json($material);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $material = Material::findOrFail($id);
        $this->authorize('update', $material);
        // TODO: обновить материал
        return response()->json(['message' => 'Not implemented']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $material = Material::findOrFail($id);
        $this->authorize('delete', $material);
        // TODO: удалить материал
        return response()->json(['message' => 'Not implemented']);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        $material = Material::findOrFail($id);
        $this->authorize('markRead', $material);
        // TODO: отметить материал как прочитанный
        return response()->json(['message' => 'Not implemented']);
    }
}

