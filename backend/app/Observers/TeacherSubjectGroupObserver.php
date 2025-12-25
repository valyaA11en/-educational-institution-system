<?php

namespace App\Observers;

use App\Support\Security\AccessScopeService;

class TeacherSubjectGroupObserver
{
    public function created($assignment): void
    {
        $this->invalidateUser($assignment->teacher_user_id);
    }

    public function updated($assignment): void
    {
        $this->invalidateUser($assignment->teacher_user_id);
    }

    public function deleted($assignment): void
    {
        $this->invalidateUser($assignment->teacher_user_id);
    }

    private function invalidateUser($userId): void
    {
        AccessScopeService::invalidateForUser($userId);
    }
}

