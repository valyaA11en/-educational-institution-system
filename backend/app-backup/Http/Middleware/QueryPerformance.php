<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class QueryPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        DB::enableQueryLog();
        
        $response = $next($request);

        $queries = DB::getQueryLog();
        
        // Log queries with N+1 potential
        $this->detectNPlusOne($queries);

        // Log slow queries
        foreach ($queries as $query) {
            $time = ($query['time'] ?? 0) * 1000;
            if ($time > 100) {
                Log::warning('Slow query detected', [
                    'query' => $query['query'],
                    'time_ms' => $time,
                    'url' => $request->fullUrl(),
                ]);
            }
        }

        DB::flushQueryLog();

        return $response;
    }

    protected function detectNPlusOne(array $queries): void
    {
        $similarQueries = [];
        
        foreach ($queries as $query) {
            $normalized = preg_replace('/\d+/', '?', $query['query']);
            $similarQueries[$normalized] = ($similarQueries[$normalized] ?? 0) + 1;
        }

        foreach ($similarQueries as $query => $count) {
            if ($count > 10) {
                Log::warning('Potential N+1 query detected', [
                    'query' => $query,
                    'count' => $count,
                ]);
            }
        }
    }
}


