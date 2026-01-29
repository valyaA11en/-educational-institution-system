<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KtpTemplateController extends Controller
{
    public function index(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function store(Request $request): JsonResponse { return response()->json(['message' => 'Not implemented'], 501); }
}