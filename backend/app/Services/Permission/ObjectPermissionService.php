<?php

namespace App\Services\Permission;

use App\Models\ObjectPermission;
use App\Models\User;

class ObjectPermissionService
{
    public function hasPermission(User $user, string $permissionCode, string $objectType, int $objectId): bool
    {
        // Check if user has global permission
        $hasGlobalPermission = $user->permissions()
            ->where('code', $permissionCode)
            ->exists();

        if ($hasGlobalPermission) {
            // Check if there's a deny on this specific object
            $denied = ObjectPermission::where('object_type', $objectType)
                ->where('object_id', $objectId)
                ->whereHas('permission', fn($q) => $q->where('code', $permissionCode))
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereIn('role_id', $user->roles()->pluck('id'));
                })
                ->exists();

            return !$denied;
        }

        // Check object-level permission
        return ObjectPermission::where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->whereHas('permission', fn($q) => $q->where('code', $permissionCode))
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereIn('role_id', $user->roles()->pluck('id'));
            })
            ->exists();
    }

    public function grantPermission(string $permissionCode, string $objectType, int $objectId, ?int $userId = null, ?int $roleId = null): ObjectPermission
    {
        $permission = \App\Models\Permission::where('code', $permissionCode)->firstOrFail();

        return ObjectPermission::firstOrCreate([
            'permission_id' => $permission->id,
            'user_id' => $userId,
            'role_id' => $roleId,
            'object_type' => $objectType,
            'object_id' => $objectId,
        ]);
    }

    public function revokePermission(string $permissionCode, string $objectType, int $objectId, ?int $userId = null, ?int $roleId = null): bool
    {
        $permission = \App\Models\Permission::where('code', $permissionCode)->first();

        if (!$permission) {
            return false;
        }

        return ObjectPermission::where('permission_id', $permission->id)
            ->where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->delete() > 0;
    }
}








