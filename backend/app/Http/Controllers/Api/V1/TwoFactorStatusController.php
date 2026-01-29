<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorStatusController extends Controller
{
    /**
     * Get 2FA status
     */
    public function status(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $enabled = app(\App\Services\Auth\TwoFactorService::class)->is2FAEnabled($user);
        $row = \Illuminate\Support\Facades\DB::table('user_2fa')->where('user_id', $user->id)->first();
        $recovery = $row && !empty($row->recovery_codes_json)
            ? json_decode($row->recovery_codes_json, true)
            : [];
        $recoveryCount = is_array($recovery) ? count($recovery) : 0;

        return response()->json([
            'enabled' => $enabled,
            'recovery_codes_count' => $recoveryCount,
        ]);
    }
}