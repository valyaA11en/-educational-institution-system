<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * List all tenants
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query();

        // Search by name or slug
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        $query->withCount('users');

        $perPage = $request->get('per_page', 15);
        $tenants = $query->paginate($perPage);

        return response()->json([
            'data' => $tenants->items(),
            'pagination' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ]
        ]);
    }

    /**
     * Create a new tenant
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants,slug',
            'timezone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $slug = $request->slug ?? Str::slug($request->name);

            // Ensure slug is unique
            $originalSlug = $slug;
            $counter = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $tenant = Tenant::create([
                'name' => $request->name,
                'slug' => $slug,
                'timezone' => $request->timezone ?? 'UTC',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => $tenant
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific tenant
     */
    public function show(Request $request, $id): JsonResponse
    {
        $tenant = Tenant::with(['users', 'members'])->find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        return response()->json([
            'data' => $tenant
        ]);
    }

    /**
     * Update a tenant
     */
    public function update(Request $request, $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:tenants,slug,' . $id,
            'timezone' => 'sometimes|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = [];
            
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('slug')) {
                $updateData['slug'] = $request->slug;
            }
            if ($request->has('timezone')) {
                $updateData['timezone'] = $request->timezone;
            }

            $tenant->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $tenant
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a tenant
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        // Check if tenant has users
        if ($tenant->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete tenant with existing users'
            ], 403);
        }

        try {
            $tenant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant: ' . $e->getMessage()
            ], 500);
        }
    }
}
