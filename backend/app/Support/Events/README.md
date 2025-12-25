# Outbox Events

## Использование

```php
use App\Services\Outbox\OutboxService;
use App\Support\Events\EventTypes;

$outboxService = app(OutboxService::class);

// Записать событие
$outboxService->record(
    eventType: EventTypes::GRADE_CREATED,
    actorUserId: auth()->id(),
    entityType: 'grade',
    entityId: $grade->id,
    payload: [
        'student_id' => $grade->student_user_id,
        'value' => $grade->value,
        'subject_id' => $grade->lesson->subject_id ?? null,
    ],
    idempotencyKey: "grade-{$grade->id}-{$grade->updated_at->timestamp}"
);
```

## Команды

```bash
# Ручной запуск обработки событий
php artisan outbox:dispatch --batch-size=100

# Replay событий для канала
php artisan outbox:replay --channel=user:123 --since=-1 day --limit=100
```

## Автоматическая обработка

События обрабатываются автоматически через Horizon каждую минуту (настроено в `routes/console.php`).

## Типы событий

- `schedule.changed` - Изменение расписания
- `grade.created` - Создание оценки
- `assignment.created` - Создание задания
- `assignment.due_soon` - Скоро дедлайн задания
- `submission.status_changed` - Изменение статуса отправки
- `document.status_changed` - Изменение статуса документа
- `chat.message_created` - Создание сообщения в чате
- `notification.created` - Создание уведомления

