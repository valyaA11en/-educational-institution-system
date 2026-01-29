<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Observability\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __construct(
        private MetricsService $metricsService
    ) {}

    public function prometheus(): Response
    {
        $metrics = $this->metricsService->getMetrics();
        
        return response($metrics, 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ]);
    }

    protected function checkDatabase(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function checkRedis(): bool
    {
        try {
            \Illuminate\Support\Facades\Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

