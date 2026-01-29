<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebSocketDeliveryService
{
    /**
     * Отправить уведомление через WebSocket с гарантией доставки
     */
    public function sendWithGuarantee(Notification $notification): void
    {
        try {
            DB::beginTransaction();

            // Создаем запись о доставке
            $delivery = NotificationDelivery::create([
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'status' => 'pending',
                'attempts' => 0,
                'last_attempt_at' => null,
            ]);

            // Публикуем в WebSocket канал
            $this->publishToWebSocket($notification);

            // Отмечаем как отправленное
            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send notification via WebSocket', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Публикация в WebSocket канал
     */
    protected function publishToWebSocket(Notification $notification): void
    {
        $channel = "user.{$notification->user_id}";
        $payload = [
            'id' => $notification->id,
            'type' => $notification->type,
            'payload' => $notification->payload_json,
            'created_at' => $notification->created_at->toIso8601String(),
        ];

        // Используем Redis для публикации (Laravel WebSockets)
        Redis::publish($channel, json_encode([
            'event' => 'notification.new',
            'data' => $payload,
        ]));
    }

    /**
     * Обработка ACK от клиента
     */
    public function acknowledge(int $notificationId, int $userId): bool
    {
        try {
            $delivery = NotificationDelivery::where('notification_id', $notificationId)
                ->where('user_id', $userId)
                ->first();

            if (!$delivery) {
                return false;
            }

            $delivery->update([
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
            ]);

            // Обновляем статус уведомления
            Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->update(['read_at' => now()]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to acknowledge notification', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить непрочитанные уведомления для replay
     */
    public function getUnacknowledgedNotifications(int $userId, ?string $lastAckId = null): array
    {
        $query = NotificationDelivery::where('user_id', $userId)
            ->where('status', '!=', 'acknowledged')
            ->with('notification')
            ->orderBy('created_at', 'asc');

        if ($lastAckId) {
            // Получаем только те, что были созданы после последнего ACK
            $query->where('id', '>', $lastAckId);
        }

        $deliveries = $query->get();

        return $deliveries->map(function ($delivery) {
            $notification = $delivery->notification;
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'payload' => $notification->payload_json,
                'created_at' => $notification->created_at->toIso8601String(),
                'delivery_id' => $delivery->id,
            ];
        })->toArray();
    }

    /**
     * Повторная отправка неподтвержденных уведомлений
     */
    public function replayUnacknowledged(int $userId): int
    {
        $deliveries = NotificationDelivery::where('user_id', $userId)
            ->where('status', 'sent')
            ->whereNull('acknowledged_at')
            ->where('created_at', '>', now()->subDays(7)) // Только за последние 7 дней
            ->with('notification')
            ->get();

        $sent = 0;
        foreach ($deliveries as $delivery) {
            try {
                $this->publishToWebSocket($delivery->notification);
                
                $delivery->increment('attempts');
                $delivery->update(['last_attempt_at' => now()]);
                
                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to replay notification', [
                    'delivery_id' => $delivery->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Обработка неотправленных уведомлений (retry механизм)
     */
    public function retryFailedDeliveries(): int
    {
        $deliveries = NotificationDelivery::where('status', 'pending')
            ->where('attempts', '<', 5) // Максимум 5 попыток
            ->where(function ($q) {
                $q->whereNull('last_attempt_at')
                  ->orWhere('last_attempt_at', '<', now()->subMinutes(5)); // Повтор через 5 минут
            })
            ->with('notification')
            ->get();

        $sent = 0;
        foreach ($deliveries as $delivery) {
            try {
                $this->publishToWebSocket($delivery->notification);
                
                $delivery->increment('attempts');
                $delivery->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'last_attempt_at' => now(),
                ]);
                
                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to retry notification delivery', [
                    'delivery_id' => $delivery->id,
                    'error' => $e->getMessage(),
                ]);
                
                if ($delivery->attempts >= 5) {
                    $delivery->update(['status' => 'failed']);
                }
            }
        }

        return $sent;
    }
}


