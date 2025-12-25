<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function lessons(Request $request): JsonResponse
    {
        // TODO: получить список уроков
        return response()->json(['message' => 'Not implemented']);
    }

    public function createLesson(Request $request): JsonResponse
    {
        // TODO: создать урок
        return response()->json(['message' => 'Not implemented']);
    }

    public function grades(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Grade::class);
        // TODO: получить оценки
        return response()->json(['message' => 'Not implemented']);
    }

    public function createGrade(Request $request): JsonResponse
    {
        $lessonId = $request->input('lesson_id');
        $assignmentId = $request->input('assignment_id');
        $this->authorize('create', [Grade::class, $lessonId, $assignmentId]);
        // TODO: создать оценку
        return response()->json(['message' => 'Not implemented']);
    }

    public function updateGrade(Request $request, int $id): JsonResponse
    {
        $grade = Grade::findOrFail($id);
        $reason = $request->input('reason');
        $this->authorize('update', [$grade, $reason]);
        // TODO: обновить оценку
        return response()->json(['message' => 'Not implemented']);
    }

    public function attendance(Request $request): JsonResponse
    {
        // TODO: получить посещаемость
        return response()->json(['message' => 'Not implemented']);
    }

    public function createAttendance(Request $request): JsonResponse
    {
        // TODO: создать запись о посещаемости
        return response()->json(['message' => 'Not implemented']);
    }

    public function reports(Request $request): JsonResponse
    {
        // TODO: получить отчеты по журналу
        return response()->json(['message' => 'Not implemented']);
    }
}

