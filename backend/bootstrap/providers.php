<?php

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\WebSocketsServiceProvider::class,
    App\Providers\DomPdfServiceProvider::class,
    Laravel\Horizon\HorizonServiceProvider::class,
    BeyondCode\LaravelWebSockets\WebSocketsServiceProvider::class,
];

// Only register Telescope in development environments
if (app()->environment(['local', 'dev', 'development'])) {
    $providers[] = Laravel\Telescope\TelescopeServiceProvider::class;
}

return $providers;
