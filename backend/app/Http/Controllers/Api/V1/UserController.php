<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: получить список пользователей с фильтрацией и пагинацией
        return response()->json(['message' => 'Not implemented']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // TODO: получить информацию о пользователе
        return response()->json(['message' => 'Not implemented']);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: создать нового пользователя
        return response()->json(['message' => 'Not implemented']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: обновить пользователя
        return response()->json(['message' => 'Not implemented']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // TODO: удалить пользователя
        return response()->json(['message' => 'Not implemented']);
    }
}

