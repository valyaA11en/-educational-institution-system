<?php

namespace App\Events;

use App\Models\OutboxEvent;
use App\Support\DTO\WebSocket\WebSocketPayloadDTO;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OutboxEventBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly WebSocketPayloadDTO $payload;

    public function __construct(
        public OutboxEvent $event
    ) {
        $this->payload = WebSocketPayloadDTO::fromOutboxEvent($event);
    }

    public function broadcastOn(): Channel|array
    {
        $channels = $this->determineChannels();

        return array_map(fn ($name) => new Channel($name), $channels);
    }

    protected function determineChannels(): array
    {
        $channels = [];

        // Determine channels based on event type and payload
        match ($this->event->event_type) {
            \App\Support\Events\EventTypes::SCHEDULE_CHANGED => $this->addScheduleChannels($channels),
            \App\Support\Events\EventTypes::GRADE_CREATED => $this->addGradeChannels($channels),
            \App\Support\Events\EventTypes::ASSIGNMENT_CREATED => $this->addAssignmentChannels($channels),
            \App\Support\Events\EventTypes::ASSIGNMENT_DUE_SOON => $this->addAssignmentDueSoonChannels($channels),
            \App\Support\Events\EventTypes::SUBMISSION_STATUS_CHANGED => $this->addSubmissionChannels($channels),
            \App\Support\Events\EventTypes::DOCUMENT_STATUS_CHANGED => $this->addDocumentChannels($channels),
            \App\Support\Events\EventTypes::CHAT_MESSAGE_CREATED => $this->addChatChannels($channels),
            \App\Support\Events\EventTypes::NOTIFICATION_CREATED => $this->addNotificationChannels($channels),
            default => $channels[] = 'global',
        };

        // Fallback to global if no channels determined
        if (empty($channels)) {
            $channels[] = 'global';
        }

        return $channels;
    }

    protected function addScheduleChannels(array &$channels): void
    {
        // Schedule changes: notify group and teacher
        if (isset($this->event->payload_json['group_id'])) {
            $channels[] = "group.{$this->event->payload_json['group_id']}";
        }
        if (isset($this->event->payload_json['teacher_id'])) {
            $channels[] = "teacher.{$this->event->payload_json['teacher_id']}";
        }
    }

    protected function addGradeChannels(array &$channels): void
    {
        // Grade created: notify student
        if (isset($this->event->payload_json['student_id'])) {
            $channels[] = "user.{$this->event->payload_json['student_id']}";
        }
    }

    protected function addAssignmentChannels(array &$channels): void
    {
        // Assignment created: notify group and teacher
        if (isset($this->event->payload_json['group_id'])) {
            $channels[] = "group.{$this->event->payload_json['group_id']}";
        }
        if (isset($this->event->payload_json['teacher_id'])) {
            $channels[] = "teacher.{$this->event->payload_json['teacher_id']}";
        }
    }

    protected function addAssignmentDueSoonChannels(array &$channels): void
    {
        // Assignment due soon: notify students
        if (isset($this->event->payload_json['student_id'])) {
            $channels[] = "user.{$this->event->payload_json['student_id']}";
        } elseif (isset($this->event->payload_json['group_id'])) {
            $channels[] = "group.{$this->event->payload_json['group_id']}";
        }
    }

    protected function addSubmissionChannels(array &$channels): void
    {
        // Submission status changed: notify student and teacher
        if (isset($this->event->payload_json['student_id'])) {
            $channels[] = "user.{$this->event->payload_json['student_id']}";
        }
        if (isset($this->event->payload_json['teacher_id'])) {
            $channels[] = "teacher.{$this->event->payload_json['teacher_id']}";
        }
    }

    protected function addDocumentChannels(array &$channels): void
    {
        // Document status changed: notify relevant users
        if (isset($this->event->payload_json['user_id'])) {
            $channels[] = "user.{$this->event->payload_json['user_id']}";
        }
    }

    protected function addChatChannels(array &$channels): void
    {
        // Chat message: notify thread members
        if (isset($this->event->payload_json['thread_id'])) {
            $channels[] = "chat.{$this->event->payload_json['thread_id']}";
        }
    }

    protected function addNotificationChannels(array &$channels): void
    {
        // Notification: notify user
        if (isset($this->event->payload_json['user_id'])) {
            $channels[] = "user.{$this->event->payload_json['user_id']}";
        }
    }

    public function broadcastAs(): string
    {
        return 'event';
    }

    public function broadcastWith(): array
    {
        return $this->payload->toArray();
    }
}


