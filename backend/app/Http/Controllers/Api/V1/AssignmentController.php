<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Assignment::class);
        // TODO: получить список заданий
        return response()->json(['message' => 'Not implemented']);
    }

    public function store(Request $request): JsonResponse
    {
        $subjectId = $request->input('subject_id');
        $groupId = $request->input('group_id');
        $this->authorize('create', [Assignment::class, $subjectId, $groupId]);
        // TODO: создать задание
        return response()->json(['message' => 'Not implemented']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('view', $assignment);
        // TODO: получить задание
        return response()->json(['message' => 'Not implemented']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('update', $assignment);
        // TODO: обновить задание
        return response()->json(['message' => 'Not implemented']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('delete', $assignment);
        // TODO: удалить задание
        return response()->json(['message' => 'Not implemented']);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('submit', $assignment);
        // TODO: отправить решение задания
        return response()->json(['message' => 'Not implemented']);
    }

    public function grade(Request $request, int $id, int $submissionId): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('grade', $assignment);
        // TODO: оценить решение задания
        return response()->json(['message' => 'Not implemented']);
    }
}

