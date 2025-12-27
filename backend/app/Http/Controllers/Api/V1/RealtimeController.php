<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Models\EventStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class RealtimeController extends Controller
{
    public function replay(Request $request): JsonResponse
    {
        $user = $request->user();

        $since = (int) $request->query('since', 0);
        $limit = (int) $request->query('limit', 100);
        $limit = $limit > 0 && $limit <= 500 ? $limit : 100;

        $channels = $this->extractChannels($request);

        if (empty($channels)) {
            return response()->json([
                'data' => [],
                'next_since' => null,
            ]);
        }

        // Authorize channels
        foreach ($channels as $channel) {
            if (! $this->canAccessChannel($user, $channel)) {
                abort(403, 'Forbidden channel: '.$channel);
            }
        }

        $query = EventStore::query()
            ->where('id', '>', $since)
            ->whereIn('channel_key', $channels)
            ->orderBy('id')
            ->limit($limit + 1);

        $events = $query->get();

        $nextSince = null;
        if ($events->count() > $limit) {
            $nextSince = $events->last()->id;
            $events = $events->slice(0, $limit);
        }

        return response()->json([
            'data' => $events->map(static function (EventStore $event): array {
                return [
                    'id' => $event->id,
                    'channelKey' => $event->channel_key,
                    'eventType' => $event->event_type,
                    'payload' => $event->payload_json,
                    'createdAt' => $event->created_at?->toIso8601String(),
                ];
            })->values(),
            'next_since' => $nextSince,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function extractChannels(Request $request): array
    {
        $channels = $request->query('channels', []);

        if (! is_array($channels)) {
            $channels = $channels ? [$channels] : [];
        }

        // Backwards compatibility: single "channel" param
        $single = $request->query('channel');
        if ($single) {
            $channels[] = $single;
        }

        $channels = array_filter(array_map('strval', $channels));

        return array_values(array_unique($channels));
    }

    protected function canAccessChannel(mixed $user, string $channel): bool
    {
        if (! $user) {
            return false;
        }

        // channel format: type:id (e.g. user:1, group:2, teacher:3, chat:10)
        [$type, $id] = array_pad(explode(':', $channel, 2), 2, null);

        if (! $type || ! $id) {
            return false;
        }

        $id = (int) $id;

        return match ($type) {
            'user' => $this->canAccessUserChannel($user->id, $id),
            'teacher' => $this->canAccessTeacherChannel($user->id, $id),
            'group' => $this->canAccessGroupChannel($user->id, $id),
            'chat' => $this->canAccessChatChannel($user->id, $id),
            default => false,
        };
    }

    protected function canAccessUserChannel(int $userId, int $channelUserId): bool
    {
        // user.* только себе
        return $userId === $channelUserId;
    }

    protected function canAccessTeacherChannel(int $userId, int $teacherId): bool
    {
        // teacher.* только себе
        // TODO: добавить проверку роли "teacher", пока ограничиваем только по id
        return $userId === $teacherId;
    }

    protected function canAccessGroupChannel(int $userId, int $groupId): bool
    {
        // group.* только если состоит в группе (student/curator/teacher assigned)
        // TODO: реализовать реальную проверку членства в группе, пока заглушка
        // Например:
        // return Group::query()
        //     ->where('id', $groupId)
        //     ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
        //     ->exists();

        return true;
    }

    protected function canAccessChatChannel(int $userId, int $threadId): bool
    {
        // chat.* только если пользователь является member треда
        return ChatThread::query()
            ->where('id', $threadId)
            ->whereHas('members', static function ($query) use ($userId): void {
                $query->where('user_id', $userId);
            })
            ->exists();
    }
}










