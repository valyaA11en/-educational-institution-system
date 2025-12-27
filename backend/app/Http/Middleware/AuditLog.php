<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log sensitive operations
        if ($this->shouldLog($request)) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'tenant_id' => app()->bound('tenant_id') ? app('tenant_id') : null,
                'action' => $request->method() . ' ' . $request->path(),
                'entity' => 'http_request',
                'entity_id' => null,
                'before_json' => null,
                'after_json' => $this->sanitizeRequest($request),
                'ip' => $request->ip(),
            ]);
        }

        return $response;
    }

    protected function shouldLog(Request $request): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $sensitivePaths = [
            'admin/users',
            'admin/tenants',
            'admin/webhooks',
            'auth/login',
            'auth/logout',
        ];

        foreach ($sensitivePaths as $path) {
            if (str_contains($request->path(), $path)) {
                return true;
            }
        }

        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    protected function sanitizeRequest(Request $request): array
    {
        $data = $request->all();
        
        // Remove sensitive fields
        $sensitive = ['password', 'password_hash', 'secret', 'token'];
        foreach ($sensitive as $field) {
            unset($data[$field]);
        }

        return $data;
    }
}

