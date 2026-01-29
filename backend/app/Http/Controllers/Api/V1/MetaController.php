<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    /**
     * Get application metadata
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'read_only' => false,
            'maintenance_mode' => false,
            'app_name' => config('app.name', 'PDO'),
            'version' => '1.0.0',
            'environment' => config('app.env', 'production'),
        ]);
    }
}