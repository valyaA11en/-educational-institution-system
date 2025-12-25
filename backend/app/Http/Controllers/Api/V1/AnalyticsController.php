<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: получить аналитику
        return response()->json(['message' => 'Not implemented']);
    }
}

