<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Services\WebSocketDeliveryService;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        private PushNotificationService $pushService
    ) {}

    /**
     * Create a notification and send push notification
     *
     * @param int $userId
     * @param string $type
     * @param array $payload
     * @param string $channel
     * @param string $status
     * @return Notification
     */
    public function create(
        int $userId,
        string $type,
        array $payload = [],
        string $channel = 'in_app',
        string $status = 'new'
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'payload_json' => $payload,
            'channel' => $channel,
            'status' => $status,
            'read_at' => null,
        ]);

        // Send push notification if channel is 'push' and user has push subscription
        // TODO: Check user's notification settings to determine if push should be sent
        // TODO: Only send push if channel includes 'push' or user has push enabled in settings
        if ($channel === 'push' || str_contains($channel, 'push')) {
            try {
                $title = $payload['title'] ?? $payload['message'] ?? $this->getDefaultTitle($type);
                $body = $payload['body'] ?? $payload['message'] ?? $this->getDefaultMessage($type, $payload);

                $this->pushService->sendToUser($userId, [
                    'title' => $title,
                    'body' => $body,
                    'icon' => '/icons/icon-192x192.png',
                    'badge' => '/icons/icon-96x96.png',
                    'data' => [
                        'notification_id' => $notification->id,
                        'type' => $type,
                        'url' => $payload['url'] ?? null,
                        ...$payload,
                    ],
                    'tag' => $type,
                    'requireInteraction' => false,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to send push notification', [
                    'user_id' => $userId,
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail notification creation if push fails
            }
        }

        return $notification;
    }

    /**
     * Create notifications for multiple users
     *
     * @param array $userIds
     * @param string $type
     * @param array $payload
     * @param string $channel
     * @param string $status
     * @return array
     */
    public function createForUsers(
        array $userIds,
        string $type,
        array $payload = [],
        string $channel = 'in_app',
        string $status = 'new'
    ): array {
        $notifications = [];

        foreach ($userIds as $userId) {
            $notifications[] = $this->create($userId, $type, $payload, $channel, $status);
        }

        return $notifications;
    }

    /**
     * Create notification for users with specific role
     *
     * @param string $roleName
     * @param string $type
     * @param array $payload
     * @param string $channel
     * @param string $status
     * @return array
     */
    public function createForRole(
        string $roleName,
        string $type,
        array $payload = [],
        string $channel = 'in_app',
        string $status = 'new'
    ): array {
        $users = User::whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->pluck('id')->toArray();

        return $this->createForUsers($users, $type, $payload, $channel, $status);
    }

    /**
     * Mark notification as read
     *
     * @param int $notificationId
     * @param int $userId
     * @return bool
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return true;
    }

    /**
     * Mark all notifications as read for user
     *
     * @param int $userId
     * @return int
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get default title for notification type
     */
    private function getDefaultTitle(string $type): string
    {
        return match ($type) {
            'document.requires_approval' => 'Требуется согласование документа',
            'ticket.overdue' => 'Просроченный тикет',
            'ticket.response_overdue' => 'Просрочен ответ на тикет',
            'ticket.assigned' => 'Тикет назначен',
            'ticket.reassigned' => 'Тикет переназначен',
            'ticket.escalated' => 'Тикет эскалирован',
            'ticket.sla_reminder' => 'Напоминание о SLA',
            'grade.low_average' => 'Низкая средняя оценка',
            'risk.high' => 'Высокий риск',
            default => 'Новое уведомление',
        };
    }

    /**
     * Get default message for notification type
     */
    private function getDefaultMessage(string $type, array $payload): string
    {
        return match ($type) {
            'document.requires_approval' => "Документ #" . ($payload['document_number'] ?? '') . " требует вашего согласования",
            'ticket.overdue' => "Тикет #" . ($payload['ticket_id'] ?? '') . " просрочен",
            'ticket.response_overdue' => "Требуется ответ на тикет #" . ($payload['ticket_id'] ?? '') . "",
            'ticket.assigned' => "Вам назначен тикет #" . ($payload['ticket_id'] ?? '') . "",
            'ticket.reassigned' => "Тикет #" . ($payload['ticket_id'] ?? '') . " был переназначен",
            'ticket.escalated' => "Тикет #" . ($payload['ticket_id'] ?? '') . " был эскалирован",
            'ticket.sla_reminder' => "Напоминание: тикет #" . ($payload['ticket_id'] ?? '') . " скоро просрочится",
            'grade.low_average' => $payload['message'] ?? 'У вас низкая средняя оценка',
            'risk.high' => $payload['message'] ?? 'Обнаружен высокий риск',
            default => $payload['message'] ?? 'У вас новое уведомление',
        };
    }
}

