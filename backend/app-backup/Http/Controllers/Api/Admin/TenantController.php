<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return response()->json($tenants);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug', 'regex:/^[a-z0-9-]+$/'],
            'timezone' => ['sometimes', 'string', 'max:255'],
        ]);

        $tenant = Tenant::create($validated);

        return response()->json($tenant, 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        return response()->json($tenant);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:tenants,slug,' . $id, 'regex:/^[a-z0-9-]+$/'],
            'timezone' => ['sometimes', 'string', 'max:255'],
        ]);

        $tenant->update($validated);

        return response()->json($tenant);
    }

    public function destroy(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted']);
    }
}

