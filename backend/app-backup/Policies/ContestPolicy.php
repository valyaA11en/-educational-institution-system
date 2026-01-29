<?php

namespace App\Policies;

use App\Models\Contest;
use App\Models\User;

class ContestPolicy extends BasePolicy
{
    public function view(User $user, Contest $contest): bool
    {
        // All can view if visibility_scope is 'all'
        if ($contest->visibility_scope === 'all') {
            return true;
        }

        // Check if user is in targets
        if ($contest->visibility_scope === 'group') {
            $isInGroup = $contest->targets()
                ->where('group_id', $user->group_id)
                ->exists();
            if ($isInGroup) {
                return true;
            }
        }

        if ($contest->visibility_scope === 'invite') {
            $isInvited = $contest->targets()
                ->where('user_id', $user->id)
                ->exists();
            if ($isInvited) {
                return true;
            }
        }

        // Jury members and admins can always view
        $isJury = $contest->jury()->where('user_id', $user->id)->exists();
        if ($isJury) {
            return true;
        }

        return $user->hasPermission('contests.manage') || $user->hasPermission('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contests.manage') || $user->hasPermission('admin');
    }

    public function update(User $user, Contest $contest): bool
    {
        return $user->hasPermission('contests.manage') || $user->hasPermission('admin');
    }

    public function delete(User $user, Contest $contest): bool
    {
        return $user->hasPermission('contests.manage') || $user->hasPermission('admin');
    }

    public function submit(User $user, Contest $contest): bool
    {
        // Check if user can participate based on visibility_scope and targets
        if ($contest->visibility_scope === 'all') {
            return true;
        }

        if ($contest->visibility_scope === 'group') {
            return $contest->targets()
                ->where('group_id', $user->group_id)
                ->exists();
        }

        if ($contest->visibility_scope === 'invite') {
            return $contest->targets()
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function score(User $user, Contest $contest): bool
    {
        // Only jury members can score
        return $contest->jury()->where('user_id', $user->id)->exists() ||
               $user->hasPermission('admin');
    }
}






