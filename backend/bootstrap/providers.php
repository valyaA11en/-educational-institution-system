<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\WebSocketsServiceProvider::class,
    Laravel\Horizon\HorizonServiceProvider::class,
    BeyondCode\LaravelWebSockets\WebSocketsServiceProvider::class,
];
