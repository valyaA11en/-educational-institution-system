<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor
    ) {}

    /**
     * Setup 2FA: generate secret, return QR URL and secret for manual entry.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $secret = $this->twoFactor->generateSecret($user);
        $qrCodeUrl = $this->twoFactor->getQRCodeUrl($user, $secret);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    /**
     * Enable 2FA: verify TOTP code, then enable. Returns recovery codes.
     */
    public function enable(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$this->twoFactor->enable($user, $request->code)) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        $recoveryCodes = $this->twoFactor->getRecoveryCodes($user);

        return response()->json([
            'message' => '2FA enabled',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA: verify TOTP or recovery code, then disable.
     */
    public function disable(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'code' => 'required|string|min:6',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$this->twoFactor->disable($user, $request->code)) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        return response()->json(['message' => '2FA disabled']);
    }
}
