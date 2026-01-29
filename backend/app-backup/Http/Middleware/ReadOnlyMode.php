<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReadOnlyMode
{
    /**
     * Routes that are allowed even in read-only mode
     */
    protected array $allowedRoutes = [
        'auth/logout',
        'auth/refresh',
        'up', // health check
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Check if read-only mode is enabled
        $isReadOnly = $this->isReadOnlyMode();

        if (!$isReadOnly) {
            return $next($request);
        }

        // Allow GET, HEAD, OPTIONS methods
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // Check if route is in allowed list
        $path = $request->path();
        $apiPath = str_replace('api/v1/', '', $path);
        
        foreach ($this->allowedRoutes as $allowedRoute) {
            if (str_contains($apiPath, $allowedRoute) || str_contains($path, $allowedRoute)) {
                return $next($request);
            }
        }

        // Block POST, PATCH, DELETE, PUT requests
        return response()->json([
            'message' => 'System in read-only mode',
        ], 503);
    }

    protected function isReadOnlyMode(): bool
    {
        try {
            $setting = DB::table('settings')
                ->where('key', 'system.read_only')
                ->first();

            if (!$setting) {
                return false;
            }

            // Handle both JSON value and direct value
            $value = $setting->value_json ?? $setting->value ?? false;
            
            // If value_json is an array, get the 'value' key
            if (is_array($value)) {
                $value = $value['value'] ?? false;
            }
            
            // Handle string values
            if (is_string($value)) {
                return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
            }

            return (bool) $value;
        } catch (\Exception $e) {
            // If settings table doesn't exist yet, allow requests
            return false;
        }
    }
}

