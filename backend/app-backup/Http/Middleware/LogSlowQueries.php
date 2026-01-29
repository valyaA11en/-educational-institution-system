<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSlowQueries
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Enable query logging
        DB::enableQueryLog();
        
        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Log if request took >= 300ms
        if ($duration >= 300) {
            $queries = DB::getQueryLog();
            $queryCount = count($queries);
            
            // Filter queries that took >= 300ms
            $slowQueries = array_filter($queries, function ($query) {
                return ($query['time'] ?? 0) * 1000 >= 300; // Query time in milliseconds
            });

            Log::channel('slow')->warning('Slow request detected', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'duration_ms' => round($duration, 2),
                'query_count' => $queryCount,
                'slow_queries' => array_map(function ($query) {
                    return [
                        'sql' => $query['query'] ?? '',
                        'bindings' => $query['bindings'] ?? [],
                        'time_ms' => round(($query['time'] ?? 0) * 1000, 2),
                    ];
                }, array_values($slowQueries)),
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);
        }

        // Clear query log to avoid memory issues
        DB::flushQueryLog();

        return $response;
    }
}

