<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSwitchController extends Controller
{
    public function switch(Request $request, $id): JsonResponse
    {
        $userId = (int) auth()->id();
        $tenantId = (int) $id;

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        $allowed = (int) auth()->user()->tenant_id === $tenantId;
        if (!$allowed && Schema::hasTable('tenant_members')) {
            $allowed = DB::table('tenant_members')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->exists();
        }
        if (!$allowed) {
            return response()->json(['message' => 'Access denied to tenant'], 403);
        }

        return response()->json([
            'message' => 'OK',
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant->name ?? null,
        ]);
    }
}
