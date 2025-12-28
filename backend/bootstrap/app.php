<?php

use App\Support\ValidateEnvironment;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use RuntimeException;

// Validate environment variables before bootstrapping
// Note: .env is loaded by Laravel's Application::configure(), but we validate early
// using getenv() and $_ENV as fallbacks
try {
    ValidateEnvironment::validate();
} catch (RuntimeException $e) {
    // In console, output to stderr
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    
    // In web context, show error page
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>Configuration Error</title></head><body><h1>Configuration Error</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre></body></html>');
}

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
        // Enable CORS for API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\AuthenticateWithToken::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'object.permission' => \App\Http\Middleware\CheckObjectPermission::class,
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'protect.refresh' => \App\Http\Middleware\ProtectRefreshEndpoint::class,
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
