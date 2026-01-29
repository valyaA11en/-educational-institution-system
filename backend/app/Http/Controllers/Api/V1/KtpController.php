<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KtpController extends Controller
{
    public function index(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function store(Request $request): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function show(Request $request, $id): JsonResponse { return response()->json(['data' => null]); }
    public function update(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function addTopics(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function progress(Request $request, $id): JsonResponse { return response()->json(['data' => []]); }
    public function applyTemplate(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function copyFrom(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function updateTopic(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function deleteTopic(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
    public function linkTopic(Request $request, $id): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
}