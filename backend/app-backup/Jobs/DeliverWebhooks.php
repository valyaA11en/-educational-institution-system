<?php

namespace App\Jobs;

use App\Models\OutboxEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // We handle retries manually via attempts
    public int $backoff = 60;

    public function __construct(
        public ?int $outboxEventId = null
    ) {
    }

    public function handle(): void
    {
        if ($this->outboxEventId) {
            // Deliver specific event
            $event = OutboxEvent::find($this->outboxEventId);
            if ($event && $event->sent_at) {
                $this->deliverEvent($event);
            }
            return;
        }

        // Process pending deliveries with retries
        $this->processPendingDeliveries();

        // Process new events that have been sent
        $this->processNewEvents();
    }

    protected function processPendingDeliveries(): void
    {
        $deliveries = WebhookDelivery::where('status', 'pending')
            ->where('attempts', '<', 10)
            ->with(['webhookEndpoint', 'outboxEvent'])
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        foreach ($deliveries as $delivery) {
            if (!$delivery->outboxEvent || !$delivery->webhookEndpoint) {
                continue;
            }

            $this->deliverToEndpoint($delivery);
        }
    }

    protected function processNewEvents(): void
    {
        $events = OutboxEvent::whereNotNull('sent_at')
            ->whereDoesntHave('webhookDeliveries')
            ->orderBy('sent_at')
            ->limit(100)
            ->get();

        foreach ($events as $event) {
            $this->deliverEvent($event);
        }
    }

    protected function deliverEvent(OutboxEvent $event): void
    {
        $webhooks = WebhookEndpoint::where('enabled', true)
            ->get()
            ->filter(fn (WebhookEndpoint $webhook) => $webhook->matchesEventType($event->event_type));

        foreach ($webhooks as $webhook) {
            // Check if delivery already exists
            $existingDelivery = WebhookDelivery::where('webhook_endpoint_id', $webhook->id)
                ->where('outbox_event_id', $event->id)
                ->first();

            if ($existingDelivery) {
                continue;
            }

            // Create delivery record
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $webhook->id,
                'outbox_event_id' => $event->id,
                'status' => 'pending',
                'attempts' => 0,
            ]);

            $this->deliverToEndpoint($delivery);
        }
    }

    protected function deliverToEndpoint(WebhookDelivery $delivery): void
    {
        $webhook = $delivery->webhookEndpoint;
        $event = $delivery->outboxEvent;

        if (!$webhook || !$event) {
            return;
        }

        // Calculate backoff delay
        $delay = min(pow(2, $delivery->attempts) * 60, 3600); // Max 1 hour
        if ($delivery->attempts > 0 && now()->diffInSeconds($delivery->updated_at) < $delay) {
            // Too soon to retry
            return;
        }

        try {
            // Prepare event payload
            $payload = [
                'eventId' => $event->id,
                'eventType' => $event->event_type,
                'actorUserId' => $event->actor_user_id,
                'entityType' => $event->entity_type,
                'entityId' => $event->entity_id,
                'payload' => $event->payload_json,
                'createdAt' => $event->created_at->toIso8601String(),
            ];

            $body = json_encode($payload);
            $signature = hash_hmac('sha256', $body, $webhook->secret);

            // Send HTTP request
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Signature' => $signature,
                    'Content-Type' => 'application/json',
                ])
                ->post($webhook->url, $payload);

            if ($response->successful()) {
                $delivery->markAsSent();
                Log::info('Webhook delivered successfully', [
                    'delivery_id' => $delivery->id,
                    'webhook_id' => $webhook->id,
                    'event_type' => $event->event_type,
                ]);
            } else {
                $error = "HTTP {$response->status()}: {$response->body()}";
                $delivery->markAsFailed($error);
                Log::warning('Webhook delivery failed', [
                    'delivery_id' => $delivery->id,
                    'webhook_id' => $webhook->id,
                    'event_type' => $event->event_type,
                    'error' => $error,
                ]);

                // Retry if attempts < max - will be picked up by scheduled job
                if ($delivery->attempts < 10) {
                    $delivery->markAsPending();
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $delivery->markAsFailed($error);
            Log::error('Webhook delivery error', [
                'delivery_id' => $delivery->id,
                'webhook_id' => $webhook->id,
                'event_type' => $event->event_type,
                'error' => $error,
                'trace' => $e->getTraceAsString(),
            ]);

            // Retry if attempts < max - will be picked up by scheduled job
            if ($delivery->attempts < 10) {
                $delivery->markAsPending();
            }
        }
    }
}

