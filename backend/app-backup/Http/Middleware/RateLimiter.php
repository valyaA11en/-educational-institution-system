<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimiter
{
    public function handle(Request $request, Closure $next, string $key = 'api', int $maxAttempts = 60): Response
    {
        $identifier = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key . ':' . $identifier, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($key . ':' . $identifier),
            ], 429);
        }

        RateLimiter::hit($key . ':' . $identifier);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($key . ':' . $identifier, $maxAttempts));

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user()) {
            return 'user:' . $user->id;
        }

        return $request->ip();
    }
}


