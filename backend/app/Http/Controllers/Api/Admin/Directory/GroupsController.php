<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class GroupsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $groups = Group::forTenant($tenantId)->with(['subgroups', 'users'])->get();
        return response()->json(['data' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:groups,code',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group = Group::create([
            'name' => $request->name,
            'code' => $request->code,
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        return response()->json(['data' => $group], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $group = Group::forTenant($tenantId)->with(['subgroups', 'users'])->findOrFail($id);
        return response()->json(['data' => $group]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $group = Group::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:groups,code,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group->update($request->only(['name', 'code']));
        return response()->json(['data' => $group->load(['subgroups', 'users'])]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $group = Group::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $group->delete();
        return response()->json(['message' => 'Group deleted successfully']);
    }
}