# Политика хранения данных

## Обзор

Данный документ описывает политику хранения и архивирования данных в системе PDO.

## Сроки хранения

### Активные данные

**Хранятся бессрочно** (пока не удалены вручную):

- **Users** (пользователи)
- **Groups** (группы)
- **Subjects** (предметы)
- **Rooms** (кабинеты)
- **Tenants** (арендаторы)

### Временные данные

#### Schedule (Расписание)

- **ScheduleVersions**: Хранятся все версии (draft, published, archived)
- **ScheduleItems**: Привязаны к версиям, удаляются при удалении версии
- **ScheduleReplacements**: Хранятся 1 год после даты замены

#### Journal (Журнал)

- **Lessons**: Хранятся бессрочно
- **Grades**: Хранятся бессрочно (требования к отчетности)
- **Attendance**: Хранятся 5 лет после окончания учебного года
- **GradePeriodSummaries**: Хранятся бессрочно

#### Assignments (Задания)

- **Assignments**: Хранятся 3 года после окончания срока сдачи
- **Submissions**: Хранятся 3 года после окончания срока сдачи
- **SubmissionFiles**: Удаляются вместе с submissions

#### Documents (Документы)

- **Documents**: Хранятся бессрочно (юридические требования)
- **DocumentRoutes**: Хранятся вместе с documents
- **DocumentAcks**: Хранятся вместе с documents

#### Chat (Чат)

- **ChatMessages**: Хранятся 1 год после последнего сообщения в thread
- **ChatThreads**: Удаляются если нет сообщений за 1 год
- **ChatReports**: Хранятся 2 года

#### Notifications (Уведомления)

- **Notifications**: Хранятся 90 дней после создания
- **NotificationSettings**: Хранятся бессрочно

#### Tickets (Тикеты)

- **Tickets**: Хранятся 2 года после закрытия
- **TicketMessages**: Хранятся вместе с tickets

#### Audit Log (Аудит)

- **AuditLog**: Хранятся 7 лет (требования к аудиту)
- **AuthAttempts**: Хранятся 90 дней

#### Events (События)

- **OutboxEvents**: Хранятся 90 дней после отправки
- **EventStore**: Хранятся 1 год для replay

#### Files (Файлы)

- **Files**: Удаляются при удалении связанных сущностей
- **MinIO/S3**: Очистка через lifecycle policies (30 дней для temp файлов)

## Архивирование

### Автоматическое архивирование

#### Schedule Versions

Старые версии расписания автоматически архивируются при публикации новой:

```php
// При публикации новой версии
$oldVersion->status = 'archived';
$oldVersion->save();
```

#### Attendance Records

Посещаемость архивируется после окончания учебного года:

```bash
# Artisan команда для архивирования
php artisan attendance:archive --year=2023
```

### Ручное архивирование

#### Documents

Документы можно пометить как архивные:

```php
$document->status = 'archived';
$document->save();
```

## Удаление данных

### Мягкое удаление (Soft Delete)

Некоторые модели используют soft delete:

- **Users**: `deleted_at` timestamp
- **Groups**: `deleted_at` timestamp
- **Documents**: `deleted_at` timestamp

Мягко удаленные записи:
- Не отображаются в обычных запросах
- Сохраняются в БД для восстановления
- Удаляются окончательно через 30 дней

### Окончательное удаление

#### Автоматическое удаление

```bash
# Удаление старых notifications (90 дней)
php artisan notifications:cleanup

# Удаление старых chat messages (1 год)
php artisan chat:cleanup

# Удаление старых outbox events (90 дней)
php artisan outbox:cleanup
```

#### Ручное удаление

```bash
# Удаление старых audit logs (7 лет)
php artisan audit:cleanup --older-than=7years

# Удаление старых attendance (5 лет)
php artisan attendance:cleanup --older-than=5years
```

## Миграция данных

### Холодное хранилище

Старые данные можно мигрировать в холодное хранилище:

1. **Экспорт**: `pg_dump` для PostgreSQL
2. **Сжатие**: gzip
3. **Хранение**: S3 Glacier или аналогичное

### Процедура миграции

```bash
# 1. Экспорт старых данных
pg_dump -t attendance_2020 -Fc > attendance_2020.dump

# 2. Загрузка в холодное хранилище
aws s3 cp attendance_2020.dump s3://pdo-archive/attendance_2020.dump --storage-class GLACIER

# 3. Удаление из основной БД (после проверки)
psql -c "DELETE FROM attendance WHERE year = 2020;"
```

## Compliance

### GDPR

- **Право на удаление**: Пользователи могут запросить удаление своих данных
- **Право на доступ**: Пользователи могут запросить копию своих данных
- **Право на исправление**: Пользователи могут исправить свои данные

### Образовательные требования

- **Отчетность**: Оценки и посещаемость хранятся согласно требованиям
- **Аудит**: Audit logs хранятся для проверок

## Резервное копирование

См. `docs/DR.md` для деталей по backup и restore процедурам.

## Мониторинг использования

### Метрики

- Размер БД
- Количество записей по таблицам
- Размер файлов в MinIO/S3

### Алерты

- БД приближается к лимиту (> 80%)
- S3 приближается к лимиту (> 80%)
- Большое количество старых записей

## Планирование очистки

### Cron Jobs

```php
// В routes/console.php
Schedule::command('notifications:cleanup')->daily();
Schedule::command('chat:cleanup')->weekly();
Schedule::command('outbox:cleanup')->daily();
Schedule::command('audit:cleanup')->monthly();
```

### Ручной запуск

```bash
# Очистка всех устаревших данных
php artisan cleanup:all

# Очистка с dry-run (проверка без удаления)
php artisan cleanup:all --dry-run
```

