<?php

namespace App\Support\Events;

class EventTypes
{
    // Schedule events
    public const SCHEDULE_CHANGED = 'schedule.changed';

    // Journal events
    public const GRADE_CREATED = 'grade.created';

    // Assignment events
    public const ASSIGNMENT_CREATED = 'assignment.created';
    public const ASSIGNMENT_DUE_SOON = 'assignment.due_soon';
    public const SUBMISSION_STATUS_CHANGED = 'submission.status_changed';

    // Document events
    public const DOCUMENT_STATUS_CHANGED = 'document.status_changed';

    // Chat events
    public const CHAT_MESSAGE_CREATED = 'chat.message_created';

    // Notification events
    public const NOTIFICATION_CREATED = 'notification.created';

    public static function all(): array
    {
        return [
            self::SCHEDULE_CHANGED,
            self::GRADE_CREATED,
            self::ASSIGNMENT_CREATED,
            self::ASSIGNMENT_DUE_SOON,
            self::SUBMISSION_STATUS_CHANGED,
            self::DOCUMENT_STATUS_CHANGED,
            self::CHAT_MESSAGE_CREATED,
            self::NOTIFICATION_CREATED,
        ];
    }
}

