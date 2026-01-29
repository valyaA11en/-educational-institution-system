<?php

namespace App\Policies;

use App\Models\User;

abstract class BasePolicy
{
    /**
     * Determine if the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // TODO: implement permission check
        return false;
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, mixed $model): bool
    {
        // TODO: implement permission check
        return false;
    }

    /**
     * Determine if the user can create models.
     */
    public function create(User $user): bool
    {
        // TODO: implement permission check
        return false;
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, mixed $model): bool
    {
        // TODO: implement permission check
        return false;
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, mixed $model): bool
    {
        // TODO: implement permission check
        return false;
    }

    /**
     * Determine if the user can restore the model.
     */
    public function restore(User $user, mixed $model): bool
    {
        // TODO: implement permission check
        return false;
    }

    /**
     * Determine if the user can permanently delete the model.
     */
    public function forceDelete(User $user, mixed $model): bool
    {
        // TODO: implement permission check
        return false;
    }
}







