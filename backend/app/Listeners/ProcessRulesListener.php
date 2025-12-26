<?php

namespace App\Listeners;

use App\Events\OutboxEventBroadcast;
use App\Services\Rule\RuleEngine;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessRulesListener implements ShouldQueue
{
    public function __construct(
        private RuleEngine $ruleEngine
    ) {}

    public function handle(OutboxEventBroadcast $event): void
    {
        $this->ruleEngine->processEvent($event->event);
    }
}


