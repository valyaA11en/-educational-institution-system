<?php

namespace App\Jobs;

use App\Domains\Rules\Services\RuleEngineService;
use App\Models\OutboxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRulesForEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $outboxEventId
    ) {}

    public function handle(RuleEngineService $ruleEngine): void
    {
        $event = OutboxEvent::find($this->outboxEventId);

        if (!$event) {
            Log::warning("OutboxEvent not found for rule processing", [
                'outbox_event_id' => $this->outboxEventId,
            ]);
            return;
        }

        try {
            $ruleEngine->handleEvent(
                $event->event_type,
                $event->payload_json ?? [],
                $event->actor_user_id
            );
        } catch (\Exception $e) {
            Log::error("Failed to process rules for event", [
                'outbox_event_id' => $this->outboxEventId,
                'event_type' => $event->event_type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}


