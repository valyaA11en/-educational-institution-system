<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $hasRole = $user->roles()->where('name', $role)->exists();

        if (! $hasRole) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}


