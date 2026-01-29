<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if user has 2FA enabled
        $twoFactor = \App\Models\TwoFactorAuth::where('user_id', $user->id)
            ->where('enabled', true)
            ->first();

        if (!$twoFactor) {
            return $next($request);
        }

        // Check if 2FA verified in session
        if (!$request->session()->get('2fa_verified')) {
            return response()->json([
                'message' => 'Two-factor authentication required',
                'requires_2fa' => true,
            ], 403);
        }

        return $next($request);
    }
}


