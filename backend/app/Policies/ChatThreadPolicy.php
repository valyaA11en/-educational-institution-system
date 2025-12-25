<?php

namespace App\Policies;

use App\Models\ChatThread;
use App\Models\User;

class ChatThreadPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->permissions()->where('code', 'chat.view')->exists();
    }

    public function view(User $user, ChatThread $thread): bool
    {
        // Only members can view
        return $thread->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->permissions()->where('code', 'chat.write')->exists();
    }

    public function update(User $user, ChatThread $thread): bool
    {
        // Moderator role or chat.moderate permission
        $isModerator = $thread->members()
            ->where('user_id', $user->id)
            ->wherePivot('role_in_chat', 'moderator')
            ->exists();

        return $isModerator
            || $user->permissions()->where('code', 'chat.moderate')->exists();
    }

    public function delete(User $user, ChatThread $thread): bool
    {
        return $this->update($user, $thread);
    }

    public function write(User $user, ChatThread $thread): bool
    {
        // Only members can write
        return $this->view($user, $thread);
    }
}
