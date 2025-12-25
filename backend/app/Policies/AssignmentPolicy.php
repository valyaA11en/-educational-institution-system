<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy extends BasePolicy
{
    /**
     * Determine if the user can view any assignments.
     */
    public function viewAny(User $user): bool
    {
        // TODO: check permissions.assignments.read
        return true;
    }

    /**
     * Determine if the user can view the assignment.
     */
    public function view(User $user, Assignment $assignment): bool
    {
        // TODO: check object-level permissions
        // Student can view if assigned to them
        // Teacher can view if they created it or assigned to their subject
        return true;
    }

    /**
     * Determine if the user can create assignments.
     */
    public function create(User $user): bool
    {
        // TODO: check permissions.assignments.create
        return true;
    }

    /**
     * Determine if the user can update the assignment.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        // TODO: check object-level permissions
        // Only creator or admin can update
        return $user->id === $assignment->teacher_user_id;
    }

    /**
     * Determine if the user can delete the assignment.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        // TODO: check object-level permissions
        // Only creator or admin can delete
        return $user->id === $assignment->teacher_user_id;
    }

    /**
     * Determine if the user can submit to the assignment.
     */
    public function submit(User $user, Assignment $assignment): bool
    {
        // TODO: check if user is target of assignment (group/subgroup/individual)
        return true;
    }

    /**
     * Determine if the user can grade submissions.
     */
    public function grade(User $user, Assignment $assignment): bool
    {
        // TODO: check permissions.assignments.grade
        // Only teacher who created assignment or admin
        return $user->id === $assignment->teacher_user_id;
    }
}

