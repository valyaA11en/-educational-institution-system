<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSwitchController extends Controller
{
    public function switch(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Only admins or tenant members can switch
        $tenant = Tenant::findOrFail($id);
        
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
}


