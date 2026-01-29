<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPortfolioController extends Controller
{
    public function index(Request $request, $id): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'Not implemented yet'], 501);
    }

    public function export(Request $request, $id): JsonResponse
    {
        return response()->json(['message' => 'Export not implemented yet'], 501);
    }

    public function update(Request $request, $id, $itemId): JsonResponse
    {
        return response()->json(['message' => 'Update not implemented yet'], 501);
    }

    public function storeManual(Request $request, $id): JsonResponse
    {
        return response()->json(['message' => 'Store not implemented yet'], 501);
    }
}