<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorStatusController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $twoFactor = \App\Models\TwoFactorAuth::where('user_id', $user->id)->first();

        return response()->json([
            'enabled' => $twoFactor?->enabled ?? false,
            'has_secret' => !empty($twoFactor?->totp_secret),
        ]);
    }
}


