<?php

use App\Models\ChatThread;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User channel: only the user themselves can subscribe
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

// Group channel: user must be a member (student/curator), teacher группы или admin
Broadcast::channel('group.{id}', function (User $user, int $id) {
    // Admin имеет доступ ко всем группам
    if ($user->roles()->where('name', 'admin')->exists()) {
        return true;
    }

    // Член группы (student/curator)
    $isMember = $user->groups()->where('groups.id', $id)->exists();

    if ($isMember) {
        return true;
    }

    // Преподаватель, ведущий предмет у этой группы
    $isTeacherOfGroup = \DB::table('teacher_subject_group')
        ->where('group_id', $id)
        ->where('teacher_user_id', $user->id)
        ->exists();

    return $isTeacherOfGroup;
});

// Teacher channel: only the teacher themselves can subscribe
Broadcast::channel('teacher.{id}', function (User $user, int $id) {
    // TODO: check if user has teacher role and matches the id
    return (int) $user->id === $id;
});

// Chat channel: user must be a member of the chat thread
Broadcast::channel('chat.{threadId}', function (User $user, int $threadId) {
    return ChatThread::query()
        ->where('id', $threadId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->exists();
});
