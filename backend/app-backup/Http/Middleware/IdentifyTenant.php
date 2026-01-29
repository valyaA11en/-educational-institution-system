<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            // In dev, create demo tenant if missing
            if (app()->environment(['local', 'dev', 'development'])) {
                $tenant = Tenant::firstOrCreate(
                    ['slug' => 'demo'],
                    ['name' => 'Demo Tenant', 'timezone' => 'Europe/Amsterdam']
                );
            } else {
                return response()->json(['message' => 'Tenant not found'], 404);
            }
        }

        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        // Set timezone for tenant
        if ($tenant->timezone) {
            date_default_timezone_set($tenant->timezone);
            config(['app.timezone' => $tenant->timezone]);
        }

        // Set tenant context for database queries
        try {
            DB::statement("SET app.current_tenant_id = {$tenant->id}");
        } catch (\Exception $e) {
            // Ignore if PostgreSQL extension not available
        }

        return $next($request);
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        // 1. Check X-Tenant header (slug or ID)
        if ($tenantHeader = $request->header('X-Tenant')) {
            if (is_numeric($tenantHeader)) {
                return Tenant::find($tenantHeader);
            }
            return Tenant::where('slug', $tenantHeader)->first();
        }

        // 2. Check JWT token for tenant_id claim
        try {
            $token = \Tymon\JWTAuth\Facades\JWTAuth::getToken();
            if ($token) {
                $payload = \Tymon\JWTAuth\Facades\JWTAuth::getPayload($token);
                if ($payload->has('tenant_id')) {
                    $tenantId = $payload->get('tenant_id');
                    return Tenant::find($tenantId);
                }
            }
        } catch (\Exception $e) {
            // Token not available or invalid, continue with other methods
        }

        // 3. Check query parameter (dev only)
        if (app()->environment(['local', 'dev', 'development'])) {
            if ($tenantSlug = $request->query('tenant')) {
                return Tenant::where('slug', $tenantSlug)->first();
            }
        }

        // 4. Check subdomain (e.g., tenantSlug.example.com)
        $host = $request->getHost();
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            // Skip common subdomains
            if (!in_array($subdomain, ['www', 'api', 'admin'])) {
                return Tenant::where('slug', $subdomain)->first();
            }
        }

        // 5. Default tenant (demo for dev, or first active)
        if (app()->environment(['local', 'dev', 'development'])) {
            return Tenant::where('slug', 'demo')->first();
        }

        return null;
    }
}

