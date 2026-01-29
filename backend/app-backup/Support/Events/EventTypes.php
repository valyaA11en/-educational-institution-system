<?php

namespace App\Support\Events;

class EventTypes
{
    // Schedule events
    public const SCHEDULE_CHANGED = 'schedule.changed';
    public const SCHEDULE_VERSION_PUBLISHED = 'schedule.version_published';

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
    public const CHAT_REPORT_CREATED = 'chat.report_created';
    public const CHAT_THREAD_SETTINGS_CHANGED = 'chat.thread_settings_changed';

    // Notification events
    public const NOTIFICATION_CREATED = 'notification.created';

    // Ticket events
    public const TICKET_CREATED = 'ticket.created';
    public const TICKET_UPDATED = 'ticket.updated';
    public const TICKET_STATUS_CHANGED = 'ticket.status_changed';
    public const TICKET_PRIORITY_CHANGED = 'ticket.priority_changed';
    public const TICKET_ASSIGNED = 'ticket.assigned';
    public const TICKET_MESSAGE_CREATED = 'ticket.message_created';
    public const TICKET_FIRST_RESPONSE = 'ticket.first_response';
    public const TICKET_RESOLVED = 'ticket.resolved';
    public const TICKET_OVERDUE = 'ticket.overdue';
    public const TICKET_ESCALATED = 'ticket.escalated';

    // Risk events
    public const RISKS_UPDATED = 'risks.updated';

    // Exam events
    public const EXAM_CREATED = 'exam.created';
    public const EXAM_UPDATED = 'exam.updated';
    public const EXAM_REMINDER = 'exam.reminder';

    // Contest events
    public const CONTEST_CREATED = 'contest.created';
    public const CONTEST_UPDATED = 'contest.updated';
    public const CONTEST_REGISTRATION = 'contest.registration';
    public const CONTEST_SUBMISSION = 'contest.submission';
    public const CONTEST_RESULTS_PUBLISHED = 'contest.results_published';

    public static function all(): array
    {
        return [
            self::SCHEDULE_CHANGED,
            self::SCHEDULE_VERSION_PUBLISHED,
            self::GRADE_CREATED,
            self::ASSIGNMENT_CREATED,
            self::ASSIGNMENT_DUE_SOON,
            self::SUBMISSION_STATUS_CHANGED,
            self::DOCUMENT_STATUS_CHANGED,
            self::CHAT_MESSAGE_CREATED,
            self::CHAT_REPORT_CREATED,
            self::CHAT_THREAD_SETTINGS_CHANGED,
            self::NOTIFICATION_CREATED,
            self::TICKET_CREATED,
            self::TICKET_UPDATED,
            self::TICKET_STATUS_CHANGED,
            self::TICKET_PRIORITY_CHANGED,
            self::TICKET_ASSIGNED,
            self::TICKET_MESSAGE_CREATED,
            self::TICKET_FIRST_RESPONSE,
            self::TICKET_RESOLVED,
            self::TICKET_OVERDUE,
            self::TICKET_ESCALATED,
            self::RISKS_UPDATED,
            self::EXAM_CREATED,
            self::EXAM_UPDATED,
            self::EXAM_REMINDER,
            self::CONTEST_CREATED,
            self::CONTEST_UPDATED,
            self::CONTEST_REGISTRATION,
            self::CONTEST_SUBMISSION,
            self::CONTEST_RESULTS_PUBLISHED,
        ];
    }
}

