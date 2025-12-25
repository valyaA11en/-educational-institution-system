<?php

namespace App\Http\Middleware;

use App\Models\UserToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithToken
{
    public function handle(Request $request, Closure $next): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $token = substr($header, 7);

        $userToken = UserToken::query()
            ->where('token', $token)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $userToken) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userToken->forceFill(['last_used_at' => now()])->save();

        $user = $userToken->user;

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        Auth::setUser($user);

        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}


