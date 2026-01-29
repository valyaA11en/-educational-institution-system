<?php

namespace App\Http\Middleware;

use App\Services\Permission\ObjectPermissionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckObjectPermission
{
    public function __construct(
        private ObjectPermissionService $objectPermissionService
    ) {}

    public function handle(Request $request, Closure $next, string $permission, string $paramName = 'id'): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $objectId = $request->route($paramName);
        $objectType = $this->getObjectTypeFromRoute($request);

        if (!$objectId || !$objectType) {
            return $next($request);
        }

        if (!$this->objectPermissionService->hasPermission($user, $permission, $objectType, (int) $objectId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }

    private function getObjectTypeFromRoute(Request $request): ?string
    {
        $route = $request->route();
        $path = $route->uri();

        if (str_contains($path, 'schedule')) {
            return 'schedule_item';
        }
        if (str_contains($path, 'assignments')) {
            return 'assignment';
        }
        if (str_contains($path, 'materials')) {
            return 'material';
        }
        if (str_contains($path, 'documents')) {
            return 'document';
        }
        if (str_contains($path, 'files')) {
            return 'file';
        }
        if (str_contains($path, 'chats')) {
            return 'chat_thread';
        }
        if (str_contains($path, 'tickets')) {
            return 'ticket';
        }

        return null;
    }
}
