<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WebSocketDeliveryService;
use App\Services\WsEventDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebSocketController extends Controller
{
    public function __construct(
        private WebSocketDeliveryService $deliveryService,
        private WsEventDeliveryService $wsEventDeliveryService
    ) {}

    /**
     * POST /api/websocket/ack
     * Подтверждение получения уведомления (legacy для NotificationDelivery)
     */
    public function acknowledge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_id' => 'required|integer|exists:notifications,id',
        ]);

        $user = Auth::user();
        $success = $this->deliveryService->acknowledge(
            $validated['notification_id'],
            $user->id
        );

        if (!$success) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['message' => 'Acknowledged']);
    }

    /**
     * POST /api/websocket/ack-batch
     * Массовое подтверждение (legacy для NotificationDelivery)
     */
    public function acknowledgeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'integer|exists:notifications,id',
        ]);

        $user = Auth::user();
        $acknowledged = 0;

        foreach ($validated['notification_ids'] as $notificationId) {
            if ($this->deliveryService->acknowledge($notificationId, $user->id)) {
                $acknowledged++;
            }
        }

        return response()->json([
            'message' => 'Acknowledged',
            'acknowledged' => $acknowledged,
            'total' => count($validated['notification_ids']),
        ]);
    }

    /**
     * POST /api/websocket/ws-ack
     * Подтверждение получения события через WS (для ws_event_deliveries)
     */
    public function wsAcknowledge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deliveryId' => 'required|integer|exists:ws_event_deliveries,id',
        ]);

        $user = Auth::user();
        $success = $this->wsEventDeliveryService->acknowledge(
            $validated['deliveryId'],
            $user->id
        );

        if (!$success) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        return response()->json(['message' => 'Acknowledged']);
    }

    /**
     * GET /api/websocket/replay
     * Получить непрочитанные уведомления для replay (legacy для NotificationDelivery)
     */
    public function replay(Request $request): JsonResponse
    {
        $user = Auth::user();
        $lastAckId = $request->input('last_ack_id');

        $notifications = $this->deliveryService->getUnacknowledgedNotifications(
            $user->id,
            $lastAckId
        );

        return response()->json([
            'notifications' => $notifications,
            'count' => count($notifications),
        ]);
    }

    /**
     * GET /api/websocket/ws-replay
     * Получить неподтвержденные события для replay (для ws_event_deliveries)
     */
    public function wsReplay(Request $request): JsonResponse
    {
        $user = Auth::user();
        $afterDeliveryId = $request->input('afterDeliveryId');

        $events = $this->wsEventDeliveryService->getUnacknowledgedEvents(
            $user->id,
            $afterDeliveryId ? (int)$afterDeliveryId : null
        );

        return response()->json([
            'events' => $events,
            'count' => count($events),
        ]);
    }

    /**
     * POST /api/websocket/replay-trigger
     * Принудительный replay неподтвержденных уведомлений (legacy)
     */
    public function triggerReplay(Request $request): JsonResponse
    {
        $user = Auth::user();
        $sent = $this->deliveryService->replayUnacknowledged($user->id);

        return response()->json([
            'message' => 'Replay triggered',
            'sent' => $sent,
        ]);
    }
}

