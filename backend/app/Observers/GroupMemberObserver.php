<?php

namespace App\Observers;

use App\Support\Security\AccessScopeService;
use Illuminate\Support\Facades\DB;

class GroupMemberObserver
{
    public function created($groupMember): void
    {
        $this->invalidateUser($groupMember->user_id);
    }

    public function updated($groupMember): void
    {
        $this->invalidateUser($groupMember->user_id);
    }

    public function deleted($groupMember): void
    {
        $this->invalidateUser($groupMember->user_id);
    }

    private function invalidateUser($userId): void
    {
        AccessScopeService::invalidateForUser($userId);
    }
}







