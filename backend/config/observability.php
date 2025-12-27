<?php

return [
    'metrics' => [
        'enabled' => env('METRICS_ENABLED', true),
        'driver' => env('METRICS_DRIVER', 'redis'),
    ],

    'tracing' => [
        'enabled' => env('TRACING_ENABLED', false),
        'driver' => env('TRACING_DRIVER', 'jaeger'),
    ],

    'alerts' => [
        'enabled' => env('ALERTS_ENABLED', true),
        'channels' => [
            'slack' => env('ALERT_SLACK_WEBHOOK'),
            'email' => env('ALERT_EMAIL'),
        ],
    ],
];


