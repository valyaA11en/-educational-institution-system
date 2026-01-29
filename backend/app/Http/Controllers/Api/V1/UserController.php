<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $q = User::where('tenant_id', $tenantId)->orderBy('id');
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $s = '%' . $request->q . '%';
            $q->where(function ($q) use ($s) {
                $q->where('fio', 'like', $s)
                    ->orWhere('email', 'like', $s)
                    ->orWhere('phone', 'like', $s);
            });
        }
        $perPage = max(1, min(100, (int) ($request->per_page ?? 15)));
        $users = $q->paginate($perPage);
        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'fio' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:64|unique:users,phone',
            'password' => 'required|string|min:8',
            'status' => 'nullable|in:active,blocked',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $user = User::create([
            'fio' => $request->fio,
            'email' => $request->email,
            'phone' => $request->phone,
            'password_hash' => Hash::make($request->password),
            'status' => $request->status ?? 'active',
            'tenant_id' => $tenantId,
        ]);
        return response()->json(['data' => $user], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $user = User::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json(['data' => $user]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'fio' => 'sometimes|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:64|unique:users,phone,' . $id,
            'password' => 'nullable|string|min:8',
            'status' => 'sometimes|in:active,blocked',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $v->errors()], 422);
        }
        $tenantId = (int) auth()->user()->tenant_id;
        $user = User::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $upd = array_filter([
            'fio' => $request->fio,
            'email' => $request->email,
            'phone' => $request->phone,
            'status' => $request->status,
        ], fn ($x) => $x !== null);
        if ($request->filled('password')) {
            $upd['password_hash'] = Hash::make($request->password);
        }
        $user->update($upd);
        return response()->json(['data' => $user->fresh()]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $user = User::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        if ((int) $id === (int) auth()->id()) {
            return response()->json(['message' => 'Cannot delete yourself'], 422);
        }
        $user->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
