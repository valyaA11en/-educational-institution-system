<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // TODO: реализовать проверку прав на основе RBAC
        // Проверка через permissions модели или через роли
        $hasPermission = $user->permissions()
            ->where('code', $permission)
            ->exists();

        if (! $hasPermission) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}


