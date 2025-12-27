<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\RouteServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        channels: __DIR__ . '/../routes/channels.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\AuthenticateWithToken::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'object.permission' => \App\Http\Middleware\CheckObjectPermission::class,
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
        ]);
        
        // Add security headers
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        
        // Add tenant identification early
        $middleware->prepend(\App\Http\Middleware\IdentifyTenant::class);
        
        // Add read-only mode check
        $middleware->append(\App\Http\Middleware\ReadOnlyMode::class);
        
        // Add audit logging
        $middleware->append(\App\Http\Middleware\AuditLog::class);
        
        // Add metrics tracking
        $middleware->append(\App\Http\Middleware\TrackMetrics::class);
        
        // Add rate limiting to API routes
        $middleware->throttleApi('60,1');
        
        // Add query performance monitoring (only in non-production)
        if (app()->environment(['local', 'dev', 'development'])) {
            $middleware->append(\App\Http\Middleware\QueryPerformance::class);
            $middleware->append(\App\Http\Middleware\LogSlowQueries::class);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
