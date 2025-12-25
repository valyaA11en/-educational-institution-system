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
        // TODO: получить список материалов
        return response()->json(['message' => 'Not implemented']);
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
        $material = Material::findOrFail($id);
        $this->authorize('view', $material);
        // TODO: получить материал
        return response()->json(['message' => 'Not implemented']);
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

