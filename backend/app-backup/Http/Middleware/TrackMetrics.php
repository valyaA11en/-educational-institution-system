<?php

namespace App\Http\Middleware;

use App\Services\Observability\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackMetrics
{
    public function __construct(
        private MetricsService $metricsService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000;

        // Track request metrics
        $this->metricsService->increment('http_requests_total', [
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
        ]);

        $this->metricsService->histogram('http_request_duration_ms', $duration, [
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? 'unknown',
        ]);

        return $response;
    }
}


