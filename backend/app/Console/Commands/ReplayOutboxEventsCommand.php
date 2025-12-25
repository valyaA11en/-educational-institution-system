<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReplayOutboxEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:replay 
                            {--channel= : Channel key (e.g., user:123)}
                            {--since= : ISO 8601 datetime or relative time (e.g., 2024-01-01T00:00:00Z or -1 day)}
                            {--limit=100 : Maximum number of events to replay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replay events from event_store for a specific channel';

    public function handle(): int
    {
        $channel = $this->option('channel');
        $since = $this->option('since');
        $limit = (int) $this->option('limit');

        if (! $channel) {
            $this->error('Channel is required. Use --channel=user:123');

            return self::FAILURE;
        }

        $query = DB::table('event_store')
            ->where('channel_key', $channel)
            ->orderBy('created_at');

        if ($since) {
            $sinceDate = $this->parseSince($since);
            if ($sinceDate) {
                $query->where('created_at', '>=', $sinceDate);
            }
        }

        $events = $query->limit($limit)->get();

        if ($events->isEmpty()) {
            $this->warn("No events found for channel: {$channel}");

            return self::SUCCESS;
        }

        $this->info("Found {$events->count()} events for channel: {$channel}");

        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        foreach ($events as $eventData) {
            // Create temporary OutboxEvent model for broadcasting
            $event = new OutboxEvent();
            $event->id = $eventData->id;
            $event->event_type = $eventData->event_type;
            $event->payload_json = json_decode($eventData->payload_json, true);
            $event->created_at = \Carbon\Carbon::parse($eventData->created_at);

            // Re-broadcast event
            broadcast(new \App\Events\OutboxEventBroadcast($event));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Events replayed successfully.');

        return self::SUCCESS;
    }

    protected function parseSince(?string $since): ?string
    {
        if (! $since) {
            return null;
        }

        // Try to parse as ISO 8601
        try {
            $date = new \DateTime($since);

            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // Try relative time (e.g., -1 day, -2 hours)
            try {
                $date = new \DateTime($since);

                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $this->error("Invalid since format: {$since}");

                return null;
            }
        }
    }
}

