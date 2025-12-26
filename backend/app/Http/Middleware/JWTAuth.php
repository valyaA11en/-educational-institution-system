<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();

            if (! $token) {
                return response()->json(['message' => 'Token not provided'], 401);
            }

            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            auth()->setUser($user);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalid'], 401);
        }

        return $next($request);
    }
}


