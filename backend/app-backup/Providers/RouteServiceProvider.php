<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    public function register(): void
    {
        // Service provider registration
    }

    protected function configureRateLimiting(): void
    {
        // Login rate limit: 5/min per IP + 10/min per username
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $key = $email ? 'login:' . $email : 'login:' . $request->ip();
            
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(10)->by($key),
            ];
        });

        // File presign rate limit: 30/min per user
        RateLimiter::for('file-presign', function (Request $request) {
            $user = $request->user();
            return Limit::perMinute(30)->by($user ? $user->id : $request->ip());
        });

        // Chat messages rate limit: 20/min per user
        RateLimiter::for('chat-messages', function (Request $request) {
            $user = $request->user();
            return Limit::perMinute(20)->by($user ? $user->id : $request->ip());
        });

        // Assignment submit rate limit: 10/min per user
        RateLimiter::for('assignment-submit', function (Request $request) {
            $user = $request->user();
            return Limit::perMinute(10)->by($user ? $user->id : $request->ip());
        });
    }
}

