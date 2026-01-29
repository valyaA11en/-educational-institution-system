<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalAssistantController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'Not implemented yet'], 501);
    }
}