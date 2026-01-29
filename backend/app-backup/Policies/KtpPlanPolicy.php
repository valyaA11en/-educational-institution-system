<?php

namespace App\Policies;

use App\Models\CurriculumPlan;
use App\Models\User;
use App\Support\Security\AccessScopeService;
use Illuminate\Support\Facades\DB;

class KtpPlanPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        // Методист или учитель могут видеть планы
        return $user->permissions()->where('code', 'curriculum.view')->exists()
            || $this->isMethodist($user) 
            || $this->isTeacher($user);
    }

    public function view(User $user, CurriculumPlan $plan): bool
    {
        // Admin/руководство
        $scope = AccessScopeService::forUser($user);
        if ($scope->isAdmin() || $this->isManagement($user)) {
            return true;
        }

        // Методист
        if ($this->isMethodist($user)) {
            return true;
        }

        // Учитель по предмету+группе
        if ($this->isTeacherForSubjectAndGroup($user, $plan->subject_id, $plan->group_id)) {
            return true;
        }

        // Создатель плана
        if ($plan->created_by === $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->permissions()->where('code', 'curriculum.create')->exists()
            || $this->isMethodist($user) 
            || $this->isTeacher($user);
    }

    public function update(User $user, CurriculumPlan $plan): bool
    {
        // Admin/руководство
        $scope = AccessScopeService::forUser($user);
        if ($scope->isAdmin() || $this->isManagement($user)) {
            return true;
        }

        // Методист
        if ($this->isMethodist($user)) {
            return true;
        }

        // Учитель по предмету+группе
        if ($this->isTeacherForSubjectAndGroup($user, $plan->subject_id, $plan->group_id)) {
            return true;
        }

        // Создатель плана
        if ($plan->created_by === $user->id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, CurriculumPlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    private function isMethodist(User $user): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('roles.name', 'like', '%методист%')
            ->exists();
    }

    private function isTeacher(User $user): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('roles.name', 'like', '%преподаватель%')
            ->exists();
    }

    private function isTeacherForSubjectAndGroup(User $user, int $subjectId, int $groupId): bool
    {
        return DB::table('teacher_subject_group')
            ->where('user_id', $user->id)
            ->where('subject_id', $subjectId)
            ->where('group_id', $groupId)
            ->exists();
    }

    private function isManagement(User $user): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->whereIn('roles.name', ['руководство', 'директор'])
            ->exists();
    }
}

