<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Redis;

class MetricsService
{
    public function increment(string $metric, array $tags = [], float $value = 1): void
    {
        $key = "metrics:{$metric}";
        Redis::incrbyfloat($key, $value);
        
        if (!empty($tags)) {
            foreach ($tags as $tag => $val) {
                Redis::sadd("metrics:{$metric}:tags:{$tag}", $val);
            }
        }
    }

    public function gauge(string $metric, float $value, array $tags = []): void
    {
        $key = "metrics:{$metric}";
        Redis::set($key, $value);
    }

    public function histogram(string $metric, float $value, array $tags = []): void
    {
        $key = "metrics:histogram:{$metric}";
        Redis::lpush($key, $value);
        Redis::ltrim($key, 0, 999); // Keep last 1000 values
    }

    public function getMetrics(): string
    {
        $keys = Redis::keys('metrics:*');
        $lines = [];

        foreach ($keys as $key) {
            $value = Redis::get($key);
            $metricName = str_replace('metrics:', '', $key);
            $lines[] = "# TYPE {$metricName} gauge";
            $lines[] = "{$metricName} {$value}";
        }

        return implode("\n", $lines);
    }
}

