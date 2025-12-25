<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckObjectPermission
{
    public function handle(Request $request, Closure $next, string $permission, string $paramName = 'id'): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();

        // TODO: реализовать проверку object-level permissions на основе таблицы object_permissions
        // Сейчас все запросы пропускаются без проверки
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return $next($request);
    }
}


