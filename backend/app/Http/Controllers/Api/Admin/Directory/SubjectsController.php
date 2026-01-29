<?php

namespace App\Http\Controllers\Api\Admin\Directory;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class SubjectsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $subjects = Subject::forTenant($tenantId)->get();
        return response()->json(['data' => $subjects]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:subjects,code',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subject = Subject::create([
            'name' => $request->name,
            'code' => $request->code,
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        return response()->json(['data' => $subject], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $subject = Subject::forTenant($tenantId)->findOrFail($id);
        return response()->json(['data' => $subject]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $subject = Subject::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:subjects,code,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subject->update($request->only(['name', 'code']));
        return response()->json(['data' => $subject]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $subject = Subject::forTenant(Auth::user()->tenant_id)->findOrFail($id);
        $subject->delete();
        return response()->json(['message' => 'Subject deleted successfully']);
    }
}