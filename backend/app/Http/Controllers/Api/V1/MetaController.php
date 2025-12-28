<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MetaController extends Controller
{
    /**
     * Get system metadata
     * GET /api/v1/meta
     */
    public function index(): JsonResponse
    {
        $readOnly = false;

        try {
            $setting = DB::table('settings')
                ->where('key', 'system.read_only')
                ->first();

            if ($setting) {
                $value = $setting->value_json ?? $setting->value ?? false;
                
                if (is_array($value)) {
                    $value = $value['value'] ?? false;
                }
                
                if (is_string($value)) {
                    $readOnly = in_array(strtolower($value), ['true', '1', 'yes', 'on']);
                } else {
                    $readOnly = (bool) $value;
                }
            }
        } catch (\Exception $e) {
            // If settings table doesn't exist, read-only is false
        }

        return response()->json([
            'read_only' => $readOnly,
        ]);
    }
}

