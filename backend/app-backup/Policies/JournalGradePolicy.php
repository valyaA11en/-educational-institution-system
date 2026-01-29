<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Permission\ObjectPermissionService;

class JournalGradePolicy extends BasePolicy
{
    public function __construct(
        private ObjectPermissionService $objectPermissionService
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->permissions()->where('code', 'journal.view')->exists();
    }

    public function view(User $user, mixed $grade): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        // Student can view their own grades
        if (isset($grade->student_user_id) && $user->id === $grade->student_user_id) {
            return true;
        }

        return $this->objectPermissionService->hasPermission($user, 'journal.view', 'grade', $grade->id ?? 0);
    }

    public function create(User $user): bool
    {
        return $user->permissions()->where('code', 'journal.grade.create')->exists();
    }

    public function update(User $user, mixed $grade): bool
    {
        return $user->permissions()->where('code', 'journal.grade.edit')->exists()
            || $this->objectPermissionService->hasPermission($user, 'journal.grade.edit', 'grade', $grade->id ?? 0);
    }

    public function delete(User $user, mixed $grade): bool
    {
        return $this->update($user, $grade);
    }
}









