<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;
use App\Support\Security\AccessScopeService;
use Illuminate\Support\Facades\DB;

class MaterialPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->permissions()->where('code', 'materials.view')->exists();
    }

    public function view(User $user, Material $material): bool
    {
        $scope = AccessScopeService::forUser($user);

        // Check visibility targets (like assignments)
        $targets = $material->targets()->get();

        foreach ($targets as $target) {
            if ($target->student_user_id && $target->student_user_id === $user->id) {
                return true;
            }

            if ($target->group_id) {
                if ($scope->isMemberOfGroup($target->group_id)) {
                    return true;
                }
            }

            if ($target->subgroup_id) {
                $subgroup = DB::table('subgroups')->find($target->subgroup_id);
                if ($subgroup && $scope->isMemberOfGroup($subgroup->group_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function create(User $user, ?int $subjectId = null, ?int $groupId = null): bool
    {
        $scope = AccessScopeService::forUser($user);

        if ($scope->isAdmin()) {
            return true;
        }

        if (!$scope->isTeacher()) {
            return false;
        }

        // Check if teacher assigned to subject+group
        if ($subjectId && $groupId) {
            return DB::table('teacher_subject_group')
                ->where('teacher_user_id', $user->id)
                ->where('subject_id', $subjectId)
                ->where('group_id', $groupId)
                ->exists();
        }

        return $user->permissions()->where('code', 'materials.create')->exists();
    }

    public function update(User $user, Material $material): bool
    {
        return $this->create($user, $material->subject_id, null);
    }

    public function delete(User $user, Material $material): bool
    {
        return $this->update($user, $material);
    }

    public function markRead(User $user, Material $material): bool
    {
        $scope = AccessScopeService::forUser($user);
        // Only student can mark as read (parent doesn't need readmark)
        return $scope->isStudent() && $this->view($user, $material);
    }
}
