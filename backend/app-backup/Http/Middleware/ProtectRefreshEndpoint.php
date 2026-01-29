<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectRefreshEndpoint
{
    /**
     * Protect refresh endpoint from CSRF attacks by validating Origin/Referer
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only protect refresh endpoint
        if (!$request->is('api/v1/auth/refresh')) {
            return $next($request);
        }

        $allowedOrigins = $this->getAllowedOrigins();
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');

        // If no Origin or Referer, reject (browsers should send at least one)
        if (!$origin && !$referer) {
            return response()->json([
                'message' => 'Missing Origin or Referer header',
            ], 403);
        }

        // Check Origin header
        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $originScheme = parse_url($origin, PHP_URL_SCHEME);
            $originPort = parse_url($origin, PHP_URL_PORT);
            
            $isAllowed = false;
            foreach ($allowedOrigins as $allowed) {
                $allowedParsed = parse_url($allowed);
                $allowedHost = $allowedParsed['host'] ?? null;
                $allowedScheme = $allowedParsed['scheme'] ?? 'http';
                $allowedPort = $allowedParsed['port'] ?? null;
                
                // Match host
                if ($originHost === $allowedHost) {
                    // Match scheme
                    if ($originScheme === $allowedScheme) {
                        // Match port (if specified in allowed origin)
                        if ($allowedPort === null || $originPort === $allowedPort) {
                            $isAllowed = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$isAllowed) {
                return response()->json([
                    'message' => 'Origin not allowed',
                ], 403);
            }
        }

        // Check Referer header as fallback
        if ($referer && !$origin) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $isAllowed = false;
            
            foreach ($allowedOrigins as $allowed) {
                $allowedParsed = parse_url($allowed);
                $allowedHost = $allowedParsed['host'] ?? null;
                
                if ($refererHost === $allowedHost) {
                    $isAllowed = true;
                    break;
                }
            }
            
            if (!$isAllowed) {
                return response()->json([
                    'message' => 'Referer not allowed',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Get allowed origins from config
     */
    protected function getAllowedOrigins(): array
    {
        $origins = array_filter([
            env('FRONTEND_URL', 'http://localhost:8000'),
            env('FRONTEND_URL_ALT', null),
        ]);

        // In dev, add localhost variants
        if (app()->environment(['local', 'dev', 'development'])) {
            $origins = array_merge($origins, [
                'http://localhost:3000',
                'http://localhost:5173',
                'http://localhost:8000',
            ]);
        }

        return array_unique($origins);
    }
}

