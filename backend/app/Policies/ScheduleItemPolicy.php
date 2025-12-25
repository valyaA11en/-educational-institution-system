<?php

namespace App\Policies;

use App\Models\ScheduleItem;
use App\Models\User;
use App\Support\Security\AccessScopeService;

class ScheduleItemPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        $scope = AccessScopeService::forUser($user);
        return $scope->isAdmin()
            || $user->permissions()->where('code', 'schedule.view')->exists();
    }

    public function view(User $user, ScheduleItem $scheduleItem): bool
    {
        $scope = AccessScopeService::forUser($user);

        // Admin
        if ($scope->isAdmin()) {
            return true;
        }

        // Scheduler
        if ($user->permissions()->where('code', 'schedule.edit')->exists()) {
            return true;
        }

        // Teacher sees if teacher_user_id==me or group in accessibleGroupIds
        if ($scope->isTeacher()) {
            if ($user->id === $scheduleItem->teacher_user_id) {
                return true;
            }
            $accessibleGroupIds = $scope->accessibleGroupIds();
            if (in_array($scheduleItem->group_id, $accessibleGroupIds)) {
                return true;
            }
        }

        // Student/parent if group_id accessible
        $accessibleGroupIds = $scope->accessibleGroupIds();
        if (in_array($scheduleItem->group_id, $accessibleGroupIds)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        $scope = AccessScopeService::forUser($user);
        return $scope->isAdmin()
            || $user->permissions()->where('code', 'schedule.edit')->exists();
    }

    public function update(User $user, ScheduleItem $scheduleItem): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, ScheduleItem $scheduleItem): bool
    {
        return $this->create($user);
    }

    public function forceOverride(User $user): bool
    {
        return $user->permissions()->where('code', 'schedule.force_override')->exists();
    }

    public function manageReplacements(User $user): bool
    {
        $scope = AccessScopeService::forUser($user);
        return $scope->isAdmin()
            || $user->permissions()->where('code', 'schedule.replacements.manage')->exists();
    }

    public function manageDuties(User $user): bool
    {
        $scope = AccessScopeService::forUser($user);
        return $scope->isAdmin()
            || $user->permissions()->where('code', 'schedule.duties.manage')->exists();
    }
}
