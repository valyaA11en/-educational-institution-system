<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebSocketController extends Controller
{
    public function acknowledge(Request $request): JsonResponse { return response()->json(['success' => true]); }
    public function acknowledgeBatch(Request $request): JsonResponse { return response()->json(['success' => true]); }
    public function replay(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function triggerReplay(Request $request): JsonResponse
    {
        return response()->json(['message' => 'OK', 'triggered' => true]);
    }
    public function wsAcknowledge(Request $request): JsonResponse { return response()->json(['success' => true]); }
    public function wsReplay(Request $request): JsonResponse { return response()->json(['data' => []]); }
}