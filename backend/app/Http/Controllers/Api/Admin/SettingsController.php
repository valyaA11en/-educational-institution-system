<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /**
     * List all settings
     */
    public function index(Request $request): JsonResponse
    {
        $query = Setting::query();

        // Filter by tenant
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        } else {
            $query->whereNull('tenant_id'); // Global settings by default
        }

        // Search by key
        if ($request->has('search')) {
            $query->where('key', 'ilike', "%{$request->search}%");
        }

        $perPage = $request->get('per_page', 50);
        $settings = $query->paginate($perPage);

        return response()->json([
            'data' => $settings->items(),
            'pagination' => [
                'current_page' => $settings->currentPage(),
                'last_page' => $settings->lastPage(),
                'per_page' => $settings->perPage(),
                'total' => $settings->total(),
            ]
        ]);
    }

    /**
     * Create or update a setting
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:255',
            'value' => 'required',
            'tenant_id' => 'nullable|exists:tenants,id',
            'readonly_mode' => 'nullable|boolean',
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

            $tenantId = $request->tenant_id ?? auth()->user()->tenant_id ?? null;

            // Check if setting exists
            $query = Setting::where('key', $request->key);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->whereNull('tenant_id');
            }

            $setting = $query->first();

            if ($setting) {
                // Check readonly mode
                if ($setting->readonly_mode) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Setting is in readonly mode and cannot be modified'
                    ], 403);
                }

                $setting->value_json = $request->value;
                if ($request->has('readonly_mode')) {
                    $setting->readonly_mode = $request->readonly_mode;
                }
                $setting->save();
            } else {
                $setting = Setting::create([
                    'key' => $request->key,
                    'value_json' => $request->value,
                    'tenant_id' => $tenantId,
                    'readonly_mode' => $request->readonly_mode ?? false,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Setting saved successfully',
                'data' => $setting
            ], $setting->wasRecentlyCreated ? 201 : 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific setting by key
     */
    public function show(Request $request, $key): JsonResponse
    {
        $query = Setting::where('key', $key);

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        } else {
            $query->whereNull('tenant_id');
        }

        $setting = $query->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found'
            ], 404);
        }

        return response()->json([
            'data' => $setting
        ]);
    }

    /**
     * Update a setting
     */
    public function update(Request $request, $key): JsonResponse
    {
        $query = Setting::where('key', $key);

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        } else {
            $query->whereNull('tenant_id');
        }

        $setting = $query->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found'
            ], 404);
        }

        // Check readonly mode
        if ($setting->readonly_mode) {
            return response()->json([
                'success' => false,
                'message' => 'Setting is in readonly mode and cannot be modified'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'value' => 'sometimes|required',
            'readonly_mode' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->has('value')) {
                $setting->value_json = $request->value;
            }
            if ($request->has('readonly_mode')) {
                $setting->readonly_mode = $request->readonly_mode;
            }
            $setting->save();

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'data' => $setting
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a setting
     */
    public function destroy(Request $request, $key): JsonResponse
    {
        $query = Setting::where('key', $key);

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        } else {
            $query->whereNull('tenant_id');
        }

        $setting = $query->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found'
            ], 404);
        }

        // Check readonly mode
        if ($setting->readonly_mode) {
            return response()->json([
                'success' => false,
                'message' => 'Setting is in readonly mode and cannot be deleted'
            ], 403);
        }

        try {
            $setting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete setting: ' . $e->getMessage()
            ], 500);
        }
    }
}
