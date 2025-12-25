<?php

namespace App\Observers;

use App\Models\User;
use App\Support\Security\AccessScopeService;

class UserObserver
{
    public function updated(User $user): void
    {
        // Invalidate cache when user is updated (roles might have changed)
        AccessScopeService::invalidateForUser($user->id);
    }
}

