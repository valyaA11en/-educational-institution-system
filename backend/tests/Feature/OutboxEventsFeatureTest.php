<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OutboxEvent;
use App\Services\Outbox\OutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OutboxEventsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_create_outbox_event_created(): void
    {
        $this->markTestIncomplete('TODO: implement JournalController::createGrade to record grade.created via OutboxService');
    }

    public function test_chat_message_outbox_event_created(): void
    {
        $this->markTestIncomplete('TODO: implement ChatController::sendMessage to record chat.message_created via OutboxService');
    }
}


