<?php

namespace App\Policies;

use App\Models\Risk;
use App\Models\User;
use App\Support\Security\AccessScopeService;

class RiskPolicy
{
    public function __construct(
        private AccessScopeService $accessScope
    ) {}

    public function viewAny(User $user): bool
    {
        // Curator, methodist, management, admin can view risks
        return $this->accessScope->isAdmin($user->id)
            || $user->hasPermission('analytics.view')
            || $this->accessScope->isTeacher($user->id);
    }

    public function view(User $user, Risk $risk): bool
    {
        // Student can view their own risks
        if ($user->id === $risk->user_id) {
            return true;
        }

        // Parent can view children's risks
        if ($this->accessScope->isParent($user->id)) {
            $childrenIds = $this->accessScope->childrenStudentIds($user->id);
            if (in_array($risk->user_id, $childrenIds)) {
                return true;
            }
        }

        // Curator can view risks of students in their groups
        $accessibleGroupIds = $this->accessScope->accessibleGroupIds($user->id);
        if (!empty($accessibleGroupIds)) {
            $studentGroupIds = \Illuminate\Support\Facades\DB::table('group_members')
                ->where('user_id', $risk->user_id)
                ->where('role_in_group', 'student')
                ->pluck('group_id')
                ->toArray();
            
            if (!empty(array_intersect($accessibleGroupIds, $studentGroupIds))) {
                return true;
            }
        }

        // Admin and analytics.view permission
        return $this->accessScope->isAdmin($user->id) || $user->hasPermission('analytics.view');
    }

    public function manage(User $user): bool
    {
        return $this->accessScope->isAdmin($user->id) || $user->hasPermission('analytics.manage');
    }
}


