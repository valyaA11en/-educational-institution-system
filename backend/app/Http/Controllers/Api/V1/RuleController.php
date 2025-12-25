<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: получить список правил
        return response()->json(['message' => 'Not implemented']);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: создать правило
        return response()->json(['message' => 'Not implemented']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // TODO: получить правило
        return response()->json(['message' => 'Not implemented']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // TODO: обновить правило
        return response()->json(['message' => 'Not implemented']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // TODO: удалить правило
        return response()->json(['message' => 'Not implemented']);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        // TODO: включить/выключить правило
        return response()->json(['message' => 'Not implemented']);
    }
}

