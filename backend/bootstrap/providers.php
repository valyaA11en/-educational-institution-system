<?php

$providers = [
    App\Providers\AppServiceProvider::class,
    // Temporarily disabled for troubleshooting
    // App\Providers\HorizonServiceProvider::class,
    // App\Providers\WebSocketsServiceProvider::class,
    // App\Providers\DomPdfServiceProvider::class,
    // Laravel\Horizon\HorizonServiceProvider::class,
    // BeyondCode\LaravelWebSockets\WebSocketsServiceProvider::class,
];

// Only register Telescope in development environments
// Temporarily disabled until env is properly loaded
// if (in_array($_ENV['APP_ENV'] ?? 'production', ['local', 'dev', 'development'])) {
//     $providers[] = Laravel\Telescope\TelescopeServiceProvider::class;
// }

return $providers;
