<?php

namespace App\Observers;

use App\Models\UserLinkParentChild;
use App\Support\Security\AccessScopeService;

class UserLinkParentChildObserver
{
    public function created(UserLinkParentChild $link): void
    {
        $this->invalidateUsers([$link->parent_user_id, $link->student_user_id]);
    }

    public function updated(UserLinkParentChild $link): void
    {
        $this->invalidateUsers([$link->parent_user_id, $link->student_user_id]);
    }

    public function deleted(UserLinkParentChild $link): void
    {
        $this->invalidateUsers([$link->parent_user_id, $link->student_user_id]);
    }

    private function invalidateUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            AccessScopeService::invalidateForUser($userId);
        }
    }
}

