<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRolesRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('roles');

        // TODO: Implement role filter: ?role=curator
        // if ($role = $request->query('role')) {
        //     $query->whereHas('roles', function ($q) use ($role): void {
        //         $q->where('name', 'ilike', "%{$role}%");
        //     });
        // }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search): void {
                $q->where('fio', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 50));

        return response()->json($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['password_hash'] = Hash::make($validated['password']);
        unset($validated['password']);

        $user = User::create($validated);

        if ($roleIds = $request->input('role_ids')) {
            $user->roles()->sync($roleIds);
        }

        return response()->json($user->load('roles'), Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with('roles')->findOrFail($id);

        return response()->json($user);
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();

        if (isset($validated['password']) && !empty($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
            unset($validated['password']);
        }

        $user->fill($validated);
        $user->save();

        // Sync roles if provided
        if ($roleIds = $request->input('role_ids')) {
            $user->roles()->sync($roleIds);
        }

        return response()->json($user->load('roles'));
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function assignRoles(AssignRolesRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->roles()->sync($request->input('role_ids'));

        return response()->json($user->load('roles'));
    }
}

