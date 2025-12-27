<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantContextController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        
        if (!$tenant) {
            return response()->json(['message' => 'No tenant context'], 404);
        }

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'timezone' => $tenant->timezone,
        ]);
    }

    public function switch(Request $request): JsonResponse
    {
        // Only admins can switch tenants
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'tenant_id' => ['sometimes', 'integer', 'exists:tenants,id'],
            'tenant_slug' => ['sometimes', 'string', 'exists:tenants,slug'],
        ]);

        if ($tenantId = $request->input('tenant_id')) {
            $tenant = Tenant::find($tenantId);
        } elseif ($tenantSlug = $request->input('tenant_slug')) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
        } else {
            return response()->json(['message' => 'tenant_id or tenant_slug required'], 400);
        }

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        // Check if user is member of this tenant
        $user = auth()->user();
        $isMember = \App\Models\TenantMember::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember && !$user->hasRole('admin')) {
            return response()->json(['message' => 'You are not a member of this tenant'], 403);
        }

        // Update context for this request
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        // Generate new JWT token with updated tenant_id
        $accessToken = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Tenant switched',
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'timezone' => $tenant->timezone,
            ],
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        // Only admins can list all tenants
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tenants = Tenant::query()
            ->orderBy('name')
            ->get();

        return response()->json($tenants);
    }
}

