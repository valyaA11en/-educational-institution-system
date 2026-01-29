<?php

namespace App\Console\Commands;

use App\Services\Observability\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GeneratePrometheusMetrics extends Command
{
    protected $signature = 'metrics:export';
    protected $description = 'Export metrics in Prometheus format';

    public function handle(): int
    {
        $keys = Redis::keys('metrics:*');
        $metrics = [];

        foreach ($keys as $key) {
            $value = Redis::get($key);
            $metricName = str_replace('metrics:', '', $key);
            $metrics[] = "# TYPE {$metricName} gauge";
            $metrics[] = "{$metricName} {$value}";
        }

        echo implode("\n", $metrics);
        return 0;
    }
}


