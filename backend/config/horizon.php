<?php

use Illuminate\Support\Str;

return [
    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed job, job metrics, etc.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its own
    | threshold (in seconds) before this event is fired. This can help
    | you fine tune which queues are more important to your application.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored until you manually
    | delete them. You may adjust these values based on your needs.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` command to gather metrics from your application.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to finish executing their current jobs
    | before terminating. This option is useful if you have long-running
    | jobs that you don't want to be terminated.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the queue worker
    | configuration in your `config/queue.php` file.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Balancing Strategy
    |--------------------------------------------------------------------------
    |
    | This option determines how Horizon will balance jobs across different
    | queues. The default strategy is "auto" which will balance jobs based
    | on the number of workers and the queue's wait time. You may set this
    | to "simple" to use a simple round-robin strategy.
    |
    */

    'balance' => 'auto',

    /*
    |--------------------------------------------------------------------------
    | Queue Balancing Max Shift
    |--------------------------------------------------------------------------
    |
    | This option determines the maximum number of jobs that can be shifted
    | from one queue to another during balancing. This is useful to prevent
    | one queue from being completely drained during balancing.
    |
    */

    'balance_max_shift' => 1,

    /*
    |--------------------------------------------------------------------------
    | Queue Balancing Cooldown
    |--------------------------------------------------------------------------
    |
    | This option determines the number of seconds to wait before rebalancing
    | queues. This is useful to prevent constant rebalancing when queues are
    | being processed at similar rates.
    |
    */

    'balance_cooldown' => 3,

    /*
    |--------------------------------------------------------------------------
    | Dark Mode
    |--------------------------------------------------------------------------
    |
    | This option determines whether Horizon should use a dark mode theme
    | by default. You may set this to true to enable dark mode.
    |
    */

    'dark_mode' => env('HORIZON_DARK_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | This is the environment where Horizon is running. This will determine
    | which queues are monitored and which are ignored. You may set this
    | to an array of environments to monitor multiple environments.
    |
    */

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 10,
                'maxTime' => 0,
                'maxJobs' => 0,
                'force' => false,
                'rest' => 0,
                'sleep' => 3,
                'timeout' => 60,
                'tries' => 3,
                'memory' => 128,
                'nice' => 0,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 3,
                'maxTime' => 0,
                'maxJobs' => 0,
                'force' => false,
                'rest' => 0,
                'sleep' => 3,
                'timeout' => 60,
                'tries' => 3,
                'memory' => 128,
                'nice' => 0,
            ],
        ],
    ],
];


