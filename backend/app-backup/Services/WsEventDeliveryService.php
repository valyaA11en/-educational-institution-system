<?php

namespace App\Services;

use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\WsEventDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WsEventDeliveryService
{
    /**
     * Отправить событие через WebSocket с гарантией доставки
     * 
     * @param int $userId
     * @param OutboxEvent|null $outboxEvent
     * @param array $payload
     * @param string $eventType
     * @return WsEventDelivery
     */
    public function sendEvent(int $userId, ?OutboxEvent $outboxEvent, array $payload, string $eventType = 'event'): WsEventDelivery
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $tenantId = $user->tenant_id;

            // Создаем запись о доставке
            $delivery = WsEventDelivery::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'outbox_event_id' => $outboxEvent?->id,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Публикуем в WebSocket канал с delivery_id
            $channel = "user.{$userId}";
            $message = [
                'type' => $eventType,
                'deliveryId' => $delivery->id,
                'payload' => $payload,
                'timestamp' => now()->toIso8601String(),
            ];

            Redis::publish($channel, json_encode([
                'event' => 'ws.event',
                'data' => $message,
            ]));

            DB::commit();

            return $delivery;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send event via WebSocket', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Обработка ACK от клиента
     * 
     * @param int $deliveryId
     * @param int $userId
     * @return bool
     */
    public function acknowledge(int $deliveryId, int $userId): bool
    {
        try {
            $delivery = WsEventDelivery::where('id', $deliveryId)
                ->where('user_id', $userId)
                ->first();

            if (!$delivery) {
                Log::warning('Delivery not found for ACK', [
                    'delivery_id' => $deliveryId,
                    'user_id' => $userId,
                ]);
                return false;
            }

            if ($delivery->status === 'acked') {
                // Уже подтверждено
                return true;
            }

            $delivery->update([
                'status' => 'acked',
                'acked_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to acknowledge delivery', [
                'delivery_id' => $deliveryId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить неподтвержденные события для replay
     * 
     * @param int $userId
     * @param int|null $afterDeliveryId
     * @return array
     */
    public function getUnacknowledgedEvents(int $userId, ?int $afterDeliveryId = null): array
    {
        $query = WsEventDelivery::where('user_id', $userId)
            ->where('status', 'sent')
            ->orderBy('id', 'asc');

        if ($afterDeliveryId) {
            $query->where('id', '>', $afterDeliveryId);
        }

        $deliveries = $query->with('outboxEvent')->get();

        $events = [];
        foreach ($deliveries as $delivery) {
            $payload = $delivery->outboxEvent?->payload_json ?? [];
            
            $events[] = [
                'deliveryId' => $delivery->id,
                'type' => $delivery->outboxEvent?->event_type ?? 'event',
                'payload' => $payload,
                'timestamp' => $delivery->sent_at->toIso8601String(),
            ];
        }

        return $events;
    }

    /**
     * Очистка старых подтвержденных доставок
     * 
     * @param int $daysToKeep
     * @return int
     */
    public function cleanupOldDeliveries(int $daysToKeep = 7): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        $deleted = WsEventDelivery::where('status', 'acked')
            ->where('acked_at', '<', $cutoffDate)
            ->delete();

        Log::info('Cleaned up old WebSocket event deliveries', [
            'deleted' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $deleted;
    }
}


