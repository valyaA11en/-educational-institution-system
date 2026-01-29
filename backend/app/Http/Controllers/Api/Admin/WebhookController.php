<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function store(Request $request): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function show(Request $request, $id): JsonResponse { return response()->json(['data' => null]); }
    public function update(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function destroy(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
}