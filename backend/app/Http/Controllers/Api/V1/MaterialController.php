<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: получить список материалов
        return response()->json(['message' => 'Not implemented']);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: создать материал
        return response()->json(['message' => 'Not implemented']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // TODO: получить материал
        return response()->json(['message' => 'Not implemented']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: обновить материал
        return response()->json(['message' => 'Not implemented']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // TODO: удалить материал
        return response()->json(['message' => 'Not implemented']);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        // TODO: отметить материал как прочитанный
        return response()->json(['message' => 'Not implemented']);
    }
}

