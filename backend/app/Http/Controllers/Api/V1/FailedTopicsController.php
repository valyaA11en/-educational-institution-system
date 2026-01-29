<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FailedTopicsController extends Controller
{
    public function student(Request $request, $studentId): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function complexTopics(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}