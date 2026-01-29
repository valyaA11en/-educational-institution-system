<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GostDocumentController extends Controller
{
    public function templates(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function generateOrder(Request $request): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function generateDecree(Request $request): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function show(Request $request, $id): JsonResponse { return response()->json(['data' => null]); }
    public function validateDocument(Request $request, $id): JsonResponse { return response()->json(['valid' => true]); }
}