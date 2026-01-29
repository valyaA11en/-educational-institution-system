<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTwoFactorForRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        // Check if enforce is enabled
        $enforceSetting = \App\Models\Setting::where('key', 'security.2fa.enforce_roles')
            ->value('value_json');

        if (!($enforceSetting['enabled'] ?? true)) {
            return $next($request);
        }

        $enforceRoles = $enforceSetting['roles'] ?? ['admin', 'methodist', 'management'];

        // Check if user has one of the enforced roles
        $userRoles = $user->roles()->pluck('name')->toArray();
        $hasEnforcedRole = !empty(array_intersect($userRoles, $enforceRoles));

        if (!$hasEnforcedRole) {
            return $next($request);
        }

        // Check if 2FA is enabled
        $twoFactor = \App\Models\TwoFactorAuth::where('user_id', $user->id)
            ->where('enabled', true)
            ->exists();

        if (!$twoFactor) {
            return response()->json([
                'message' => 'Two-factor authentication is required for your role',
                'requires_2fa_setup' => true,
            ], 403);
        }

        return $next($request);
    }
}


