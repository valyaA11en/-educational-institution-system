<?php

namespace App\Policies;

use App\Models\DocTemplate;
use App\Models\User;
use App\Policies\BasePolicy;

class DocTemplatePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.registry') || $user->hasPermission('templates.manage');
    }

    public function view(User $user, DocTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('documents.registry') || $user->hasPermission('templates.manage');
    }

    public function update(User $user, DocTemplate $template): bool
    {
        return $user->hasPermission('documents.registry') || $user->hasPermission('templates.manage');
    }

    public function delete(User $user, DocTemplate $template): bool
    {
        return $user->hasPermission('documents.registry') || $user->hasPermission('templates.manage');
    }
}


