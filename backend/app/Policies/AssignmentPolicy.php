<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use App\Support\Security\AccessScopeService;
use Illuminate\Support\Facades\DB;

class AssignmentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->permissions()->where('code', 'assignments.view')->exists();
    }

    public function view(User $user, Assignment $assignment): bool
    {
        $scope = AccessScopeService::forUser($user);

        // Check visibility targets (group/subgroup/individual) + group access
        $targets = $assignment->targets()->get();

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

        // Teacher who created it
        if ($user->id === $assignment->teacher_user_id) {
            return true;
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

        return $user->permissions()->where('code', 'assignments.create')->exists();
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->create($user, $assignment->subject_id, null);
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        $scope = AccessScopeService::forUser($user);

        if (!$scope->isStudent()) {
            return false;
        }

        // Student only for themselves and only if in target
        $targets = $assignment->targets()->get();

        foreach ($targets as $target) {
            if ($target->student_user_id === $user->id) {
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
                    // Check if user is in this subgroup
                    $userSubgroups = DB::table('group_members')
                        ->where('user_id', $user->id)
                        ->where('group_id', $subgroup->group_id)
                        ->join('subgroups', 'subgroups.group_id', '=', 'group_members.group_id')
                        ->where('subgroups.id', $target->subgroup_id)
                        ->exists();
                    if ($userSubgroups) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function grade(User $user, Assignment $assignment): bool
    {
        $scope = AccessScopeService::forUser($user);

        if ($scope->isAdmin()) {
            return true;
        }

        if (!$scope->isTeacher()) {
            return false;
        }

        // Check if teacher assigned to subject+group
        return DB::table('teacher_subject_group')
            ->where('teacher_user_id', $user->id)
            ->where('subject_id', $assignment->subject_id)
            ->where('group_id', function ($q) use ($assignment) {
                $q->select('group_id')
                    ->from('assignment_targets')
                    ->where('assignment_id', $assignment->id)
                    ->whereNotNull('group_id')
                    ->limit(1);
            })
            ->exists();
    }
}
