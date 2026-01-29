<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantContextController extends Controller
{
    /**
     * Get current tenant
     */
    public function current(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Get tenant by tenant_id or first tenant from tenants relationship
        $tenant = null;
        if ($user->tenant_id) {
            $tenant = \App\Models\Tenant::find($user->tenant_id);
        }
        
        if (!$tenant) {
            $tenant = $user->tenants()->first();
        }
        
        if (!$tenant) {
            return response()->json([
                'id' => 1,
                'name' => 'Default Tenant',
                'slug' => 'default',
                'timezone' => 'Europe/Moscow',
            ]);
        }

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'timezone' => $tenant->timezone ?? 'Europe/Moscow',
        ]);
    }

    /**
     * Switch tenant (admin only)
     */
    public function switch(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenantId = $request->tenant_id;

        // Check if user has access to this tenant
        $hasAccess = $user->tenants()->where('tenants.id', $tenantId)->exists();
        
        if (!$hasAccess && !$user->hasPermission('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this tenant'
            ], 403);
        }

        try {
            // Update user's current tenant
            $user->tenant_id = $tenantId;
            $user->save();

            $tenant = \App\Models\Tenant::find($tenantId);

            return response()->json([
                'success' => true,
                'message' => 'Tenant switched successfully',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'timezone' => $tenant->timezone ?? 'Europe/Moscow',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to switch tenant: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List user tenants (admin only)
     */
    public function list(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Get all user's tenants
        $tenants = $user->tenants()->get();
        
        if ($tenants->isEmpty()) {
            return response()->json([
                [
                    'id' => 1,
                    'name' => 'Default Tenant',
                    'slug' => 'default',
                    'timezone' => 'Europe/Moscow',
                ]
            ]);
        }

        return response()->json($tenants->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'timezone' => $tenant->timezone ?? 'Europe/Moscow',
            ];
        })->toArray());
    }
}