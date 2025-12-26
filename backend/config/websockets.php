<?php

return [
    /*
     * This package comes with multi tenancy support out of the box. Here you can
     * configure the different apps that can use the webSockets server.
     *
     * Optionally you can disable client events so clients cannot send messages to each other.
     */

    'apps' => [
        [
            'id' => env('PUSHER_APP_ID', 'app-id'),
            'name' => env('APP_NAME', 'Laravel'),
            'key' => env('PUSHER_APP_KEY', 'app-key'),
            'secret' => env('PUSHER_APP_SECRET', 'app-secret'),
            'path' => env('PUSHER_APP_PATH', ''),
            'capacity' => null,
            'enable_client_messages' => false,
            'enable_statistics' => true,
            'allowed_origins' => [
                env('APP_URL', 'http://localhost'),
            ],
        ],
    ],

    /*
     * This class is responsible for finding the apps. The default provider
     * will use the apps defined in this config file.
     *
     * You can create a custom provider by implementing the
     * `AppProvider` interface.
     */

    'app_provider' => BeyondCode\LaravelWebSockets\Apps\ConfigAppProvider::class,

    /*
     * This array contains the hosts of which you want to allow incoming requests.
     * Leave this empty if you want to accept requests from all hosts.
     */

    'allowed_origins' => [
        //
    ],

    /*
     * The maximum request size in kilobytes that is allowed for an incoming WebSocket request.
     */

    'max_request_size_in_kb' => 250,

    /*
     * This path will be used to register the necessary routes for the package.
     */

    'path' => 'laravel-websockets',

    /*
     * Dashboard Routes Middleware
     *
     * These middleware will be assigned to every dashboard route, giving you
     * the chance to add your own middleware to this list or change any of
     * the existing middleware. Or, you can simply stick with this list.
     */

    'middleware' => [
        'web',
        \BeyondCode\LaravelWebSockets\Http\Middleware\Authorize::class,
    ],

    'statistics' => [
        /*
         * This model will be used to store the statistics of the WebSockets server.
         * The only requirement is that the model should extend
         * `WebSocketsStatisticsEntry` provided by this package.
         */

        'model' => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatisticsEntry::class,

        /*
         * Here you can specify the interval in seconds at which statistics should be logged.
         */

        'interval_in_seconds' => 60,

        /*
         * When the clean-command is executed, all recorded statistics older than
         * the number of days specified here will be deleted.
         */

        'delete_statistics_older_than_days' => 60,

        /*
         * Only allow one statistics entry per app per minute. This will prevent
         * the database from being flooded with statistics entries.
         */

        'perform_dns_lookup' => false,
    ],

    /*
     * Define the optional SSL context for your WebSocket connections. This
     * will be used when connecting to the WebSocket server.
     */

    'ssl' => [
        'local_cert' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_CERT', null),
        'local_pk' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_PK', null),
        'passphrase' => env('LARAVEL_WEBSOCKETS_SSL_PASSPHRASE', null),
        'verify_peer' => false,
    ],

    /*
     * Channel Manager
     * This class handles how channel persistence is handled.
     * By default, persistence is stored in an array by the driver.
     * You may change this to database persistence.
     */

    'channel_manager' => \BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManager::class,
];


