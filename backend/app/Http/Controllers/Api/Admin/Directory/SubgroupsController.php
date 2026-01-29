<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Models\Subgroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class SubgroupsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $query = Subgroup::forTenant($tenantId)->with('group');
        
        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        
        $subgroups = $query->get();
        return response()->json(['data' => $subgroups]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:subgroups,code',
            'group_id' => 'required|exists:groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subgroup = Subgroup::create([
            'name' => $request->name,
            'code' => $request->code,
            'group_id' => $request->group_id,
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        return response()->json(['data' => $subgroup->load('group')], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $subgroup = Subgroup::forTenant($tenantId)->with('group')->findOrFail($id);
        return response()->json(['data' => $subgroup]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $subgroup = Subgroup::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:subgroups,code,' . $id,
            'group_id' => 'sometimes|exists:groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subgroup->update($request->only(['name', 'code', 'group_id']));
        return response()->json(['data' => $subgroup->load('group')]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $subgroup = Subgroup::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $subgroup->delete();
        return response()->json(['message' => 'Subgroup deleted successfully']);
    }
}