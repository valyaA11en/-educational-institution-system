<?php

namespace App\Jobs;

use App\Models\OutboxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchOutboxEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $batchSize = 100
    ) {
    }

    public function handle(): void
    {
        $events = OutboxEvent::query()
            ->whereIn('status', ['new', 'failed'])
            ->orderBy('created_at')
            ->limit($this->batchSize)
            ->lockForUpdate()
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        foreach ($events as $event) {
            try {
                // Skip if already processing (another worker might be handling it)
                if ($event->status === 'processing') {
                    continue;
                }

                $this->processEvent($event);
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch outbox event', [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $event->markAsFailed();

                // Re-throw to trigger job retry with backoff
                throw $e;
            }
        }
    }

    protected function processEvent(OutboxEvent $event): void
    {
        $event->markAsProcessing();

        // Broadcast to WebSocket
        $this->broadcastEvent($event);

        // Write to event_store
        $this->writeToEventStore($event);

        $event->markAsSent();

        // Process rules asynchronously via Job
        \App\Jobs\ProcessRulesForEvent::dispatch($event->id);

        // Deliver webhooks asynchronously
        \App\Jobs\DeliverWebhooks::dispatch($event->id);
    }

    protected function broadcastEvent(OutboxEvent $event): void
    {
        // Broadcast using Laravel broadcasting
        // Channel is determined in OutboxEventBroadcast::broadcastOn()
        broadcast(new \App\Events\OutboxEventBroadcast($event));
    }

    protected function writeToEventStore(OutboxEvent $event): void
    {
        $channelKey = $this->getChannelKeyForEvent($event);

        DB::table('event_store')->insert([
            'channel_key' => $channelKey,
            'event_type' => $event->event_type,
            'payload_json' => json_encode($event->payload_json),
            'created_at' => now(),
        ]);
    }

    protected function getChannelForEvent(OutboxEvent $event): string
    {
        // Map event types to channels (used for event_store channel_key)
        return match ($event->event_type) {
            \App\Support\Events\EventTypes::SCHEDULE_CHANGED => 'schedule',
            \App\Support\Events\EventTypes::GRADE_CREATED => "user.{$event->payload_json['student_id'] ?? 'all'}",
            \App\Support\Events\EventTypes::ASSIGNMENT_CREATED => 'assignments',
            \App\Support\Events\EventTypes::ASSIGNMENT_DUE_SOON => "user.{$event->payload_json['student_id'] ?? 'all'}",
            \App\Support\Events\EventTypes::SUBMISSION_STATUS_CHANGED => "user.{$event->payload_json['student_id'] ?? 'all'}",
            \App\Support\Events\EventTypes::DOCUMENT_STATUS_CHANGED => 'documents',
            \App\Support\Events\EventTypes::CHAT_MESSAGE_CREATED => "chat.{$event->payload_json['thread_id'] ?? 'all'}",
            \App\Support\Events\EventTypes::NOTIFICATION_CREATED => "user.{$event->payload_json['user_id'] ?? 'all'}",
            default => 'global',
        };
    }

    protected function getChannelKeyForEvent(OutboxEvent $event): string
    {
        // Generate channel key for event_store
        if (isset($event->payload_json['user_id'])) {
            return "user:{$event->payload_json['user_id']}";
        }

        if (isset($event->payload_json['thread_id'])) {
            return "chat:{$event->payload_json['thread_id']}";
        }

        if ($event->entity_type && $event->entity_id) {
            return "{$event->entity_type}:{$event->entity_id}";
        }

        return 'global';
    }
}

