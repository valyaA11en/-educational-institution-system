<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReadOnlyMode
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $readonly = DB::table('settings')
                ->where('key', 'system.readonly_mode')
                ->value('value');
        } catch (\Exception $e) {
            // If settings table doesn't exist yet, allow requests
            return $next($request);
        }

        if ($readonly && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return response()->json([
                'message' => 'System is in read-only mode',
            ], 503);
        }

        return $next($request);
    }
}

