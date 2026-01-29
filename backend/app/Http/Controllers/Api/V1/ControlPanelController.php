<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlPanelController extends Controller
{
    public function curator(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function methodist(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function principal(Request $request): JsonResponse { return response()->json(['data' => []]); }
}