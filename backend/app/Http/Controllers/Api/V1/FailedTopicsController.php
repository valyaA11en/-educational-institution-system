<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FailedTopicsAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FailedTopicsController extends Controller
{
    public function __construct(
        private FailedTopicsAnalysisService $analysisService
    ) {}

    public function student(int $studentId, Request $request): JsonResponse
    {
        $user = Auth::user();

        // Проверка прав: студент может видеть только свой анализ, преподаватель/админ - любого
        if ($studentId != $user->id && !$user->hasRole('admin') && !$user->hasRole('преподаватель')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $subjectId = $request->input('subject_id');

        // Анализ и получение результатов
        $this->analysisService->analyzeStudent($studentId, $subjectId);
        $topics = $this->analysisService->getFailedTopics($studentId, $subjectId);

        return response()->json(['data' => $topics]);
    }

    public function complexTopics(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Только методист и админ могут видеть сложные темы
        if (!$user->hasRole('методист') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $subjectId = $request->input('subject_id');
        if (!$subjectId) {
            return response()->json(['message' => 'subject_id required'], 400);
        }

        $limit = $request->input('limit', 10);
        $topics = $this->analysisService->getComplexTopics($subjectId, $limit);

        return response()->json(['data' => $topics]);
    }
}

