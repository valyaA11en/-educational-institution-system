<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function setup(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $secret = $this->twoFactorService->generateSecret($user);
        $qrCodeUrl = $this->twoFactorService->getQRCodeUrl($user, $secret);
        
        // Generate recovery codes (will be saved when enabled)
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($this->twoFactorService->enable($user, $request->code)) {
            $twoFactor = \App\Models\TwoFactorAuth::where('user_id', $user->id)->first();
            
            return response()->json([
                'message' => '2FA enabled',
                'recovery_codes' => $twoFactor->recovery_codes_json ?? [],
            ]);
        }

        return response()->json(['message' => 'Invalid code'], 400);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($this->twoFactorService->disable($user, $request->code)) {
            return response()->json(['message' => '2FA disabled']);
        }

        return response()->json(['message' => 'Invalid code or recovery code'], 400);
    }
}

