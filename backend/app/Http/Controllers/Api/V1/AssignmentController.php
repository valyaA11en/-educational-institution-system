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

        $query = Assignment::with(['subject', 'teacher', 'targets']);

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($groupId = $request->query('group_id')) {
            $query->whereHas('targets', function ($q) use ($groupId) {
                $q->where('group_id', $groupId);
            });
        }

        // VisibleToUserScope is applied automatically via global scope
        $assignments = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json($assignments);
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
        // VisibleToUserScope is applied automatically - will return 404 if not visible
        $assignment = Assignment::with(['subject', 'teacher', 'targets'])->findOrFail($id);
        $this->authorize('view', $assignment);

        return response()->json($assignment);
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

