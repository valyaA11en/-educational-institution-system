<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: получить список заданий
        return response()->json(['message' => 'Not implemented']);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: создать задание
        return response()->json(['message' => 'Not implemented']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // TODO: получить задание
        return response()->json(['message' => 'Not implemented']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: обновить задание
        return response()->json(['message' => 'Not implemented']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // TODO: удалить задание
        return response()->json(['message' => 'Not implemented']);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        // TODO: отправить решение задания
        return response()->json(['message' => 'Not implemented']);
    }

    public function grade(Request $request, int $id, int $submissionId): JsonResponse
    {
        // TODO: оценить решение задания
        return response()->json(['message' => 'Not implemented']);
    }
}

