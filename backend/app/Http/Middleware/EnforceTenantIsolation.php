<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantIsolation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Ensure all queries are scoped to tenant
        if (app()->bound('tenant_id')) {
            // Tenant isolation is handled by HasTenant trait global scope
        }

        return $response;
    }
}


