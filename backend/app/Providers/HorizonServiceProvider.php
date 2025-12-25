<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class HorizonServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TODO: настроить авторизацию для Horizon dashboard
        Horizon::auth(function ($request) {
            // return true / false;
            return true; // Временно разрешаем всем, нужно настроить
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}

