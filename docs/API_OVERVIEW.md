# API Overview

## Базовый URL

```
Production: https://api.example.com/api
Development: http://localhost:8000/api
```

## Аутентификация

Все защищенные endpoints требуют JWT токен в заголовке:

```http
Authorization: Bearer {access_token}
```

## Версионирование

API версионируется через префикс:

- `/api/v1/*` - текущая версия

## Основные эндпоинты

### Аутентификация

#### POST `/api/v1/auth/login`

Вход в систему.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password",
  "tenant_id": 1  // опционально
}
```

**Response (без 2FA):**
```json
{
  "access_token": "...",
  "refresh_token": "...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

**Response (с 2FA):**
```json
{
  "requires_2fa": true,
  "temp_token": "...",
  "expires_in": 300
}
```

#### POST `/api/v1/auth/refresh`

Обновление access token.

**Request:**
```json
{
  "refresh_token": "..."
}
```

**Response:**
```json
{
  "access_token": "...",
  "refresh_token": "...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

**Защита**: Проверка Origin/Referer для защиты от CSRF.

#### POST `/api/v1/auth/2fa/verify`

Проверка 2FA кода.

**Request:**
```json
{
  "temp_token": "...",
  "code": "123456"
}
```

**Response:**
```json
{
  "access_token": "...",
  "refresh_token": "...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

#### GET `/api/v1/auth/me`

Получение информации о текущем пользователе.

**Response:**
```json
{
  "id": 1,
  "fio": "Иванов Иван Иванович",
  "email": "user@example.com",
  "roles": ["student"]
}
```

### Tenant Context

#### GET `/api/v1/tenant/current`

Получение текущего tenant контекста.

**Response:**
```json
{
  "id": 1,
  "name": "Demo School",
  "slug": "demo",
  "timezone": "Europe/Amsterdam"
}
```

#### POST `/api/v1/tenant/switch`

Переключение tenant (только для админов).

**Request:**
```json
{
  "tenant_id": 2
}
```

### Directory (Справочники)

#### GET `/api/v1/directory/groups`

Список групп.

**Response:**
```json
[
  {
    "id": 1,
    "name": "Группа 1",
    "year": 2024,
    "specialty": "..."
  }
]
```

#### POST `/api/v1/directory/groups`

Создание группы (admin only).

#### GET `/api/v1/directory/subjects`

Список предметов.

#### GET `/api/v1/directory/rooms`

Список кабинетов.

#### GET `/api/v1/directory/time-slots`

Список временных слотов.

### Schedule (Расписание)

#### GET `/api/v1/schedule`

Получение расписания.

**Query Parameters:**
- `view`: `group|teacher|room`
- `id`: ID группы/преподавателя/кабинета
- `from`: Дата начала (YYYY-MM-DD)
- `to`: Дата окончания (YYYY-MM-DD)

**Response:**
```json
{
  "items": [
    {
      "id": 1,
      "date": "2024-01-01",
      "time_slot_id": 1,
      "group_id": 1,
      "subject_id": 1,
      "teacher_user_id": 1,
      "room_id": 1,
      "subgroup_id": null
    }
  ]
}
```

#### POST `/api/v1/schedule/versions`

Создание новой версии расписания (admin only).

#### GET `/api/v1/schedule/versions/{id}/items`

Получение items версии.

#### POST `/api/v1/schedule/versions/{id}/publish`

Публикация версии (admin only).

### Journal (Журнал)

#### GET `/api/v1/journal`

Получение журнала.

**Query Parameters:**
- `group_id`: ID группы
- `subject_id`: ID предмета (опционально)
- `term_id`: ID семестра (опционально)

**Response:**
```json
{
  "group": { ... },
  "subject": { ... },
  "students": [ ... ],
  "lessons": [ ... ],
  "grades": { ... }
}
```

#### POST `/api/v1/journal/grades`

Создание оценки (преподаватель).

**Request:**
```json
{
  "lesson_id": 1,
  "student_id": 1,
  "value": 5,
  "type": "exam"
}
```

### Assignments (Задания)

#### GET `/api/v1/assignments`

Список заданий.

**Query Parameters:**
- `group_id`: Фильтр по группе
- `subject_id`: Фильтр по предмету
- `status`: Фильтр по статусу

#### POST `/api/v1/assignments`

Создание задания (преподаватель).

#### POST `/api/v1/assignments/{id}/submit`

Отправка решения (студент).

**Rate Limit**: 10/min per user

#### GET `/api/v1/assignments/{id}/submissions`

Список решений (преподаватель).

### Documents (Документы)

#### GET `/api/v1/documents`

Список документов.

#### POST `/api/v1/documents`

Создание документа (admin).

#### GET `/api/v1/documents/{id}/print`

Печать документа в PDF (GOST для orders/decisions).

**Query Parameters:**
- `format`: `pdf`

#### POST `/api/v1/documents/{id}/routes`

Назначение маршрута согласования.

#### POST `/api/v1/documents/{id}/approve`

Согласование документа.

#### POST `/api/v1/documents/{id}/sign`

Подписание документа.

### Notifications (Уведомления)

#### GET `/api/v1/notifications`

Список уведомлений текущего пользователя.

**Query Parameters:**
- `type`: Фильтр по типу
- `status`: Фильтр по статусу (new, read)

**Response:**
```json
[
  {
    "id": 1,
    "type": "grade.created",
    "payload": { ... },
    "status": "new",
    "read_at": null,
    "created_at": "2024-01-01T12:00:00Z"
  }
]
```

#### POST `/api/v1/notifications/{id}/read`

Отметить как прочитанное.

#### POST `/api/v1/notifications/read-all`

Отметить все как прочитанные.

### Chat (Чат)

#### GET `/api/v1/chat/threads`

Список чат-тредов.

#### POST `/api/v1/chat/threads`

Создание треда.

#### GET `/api/v1/chat/threads/{id}/messages`

Получение сообщений треда.

#### POST `/api/v1/chat/threads/{id}/messages`

Отправка сообщения.

**Rate Limit**: 20/min per user

#### POST `/api/v1/chat/messages/{id}/report`

Жалоба на сообщение.

### Print (Печать)

#### GET `/api/print/schedule`

Печать расписания в PDF.

**Query Parameters:**
- `view`: `group|teacher|room`
- `id`: ID
- `from`: Дата начала
- `to`: Дата окончания
- `format`: `pdf`

#### GET `/api/print/journal`

Печать журнала в PDF.

**Query Parameters:**
- `groupId`: ID группы
- `subjectId`: ID предмета (опционально)
- `termId`: ID семестра (опционально)
- `format`: `pdf`

#### GET `/api/print/attendance`

Печать посещаемости в PDF.

**Query Parameters:**
- `groupId`: ID группы
- `from`: Дата начала
- `to`: Дата окончания
- `format`: `pdf`

### Admin Endpoints

#### GET `/api/admin/tenants`

Список tenants (admin only).

#### POST `/api/admin/tenants`

Создание tenant (admin only).

#### POST `/api/admin/tenants/{id}/switch`

Переключение tenant в сессии (admin only).

#### GET `/api/admin/users`

Список пользователей (admin only).

#### POST `/api/admin/users`

Создание пользователя (admin only).

#### GET `/api/admin/webhooks`

Список webhook endpoints (admin only).

#### POST `/api/admin/webhooks`

Создание webhook endpoint (admin only).

### Health & Metrics

#### GET `/api/health`

Health check endpoint.

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-01-01T12:00:00Z",
  "database": true,
  "redis": true
}
```

#### GET `/api/metrics`

Prometheus metrics (text/plain format).

## События (Events)

### WebSocket Events

События транслируются через WebSocket в реальном времени:

#### `schedule.changed`

Изменение расписания.

```json
{
  "eventId": 1,
  "eventType": "schedule.changed",
  "actorUserId": 123,
  "entityType": "ScheduleItem",
  "entityId": 456,
  "payload": {
    "version_id": 1,
    "action": "created",
    "item": { ... }
  },
  "createdAt": "2024-01-01T12:00:00Z"
}
```

#### `grade.created`

Создание оценки.

```json
{
  "eventType": "grade.created",
  "payload": {
    "student_id": 1,
    "lesson_id": 1,
    "value": 5,
    "type": "exam"
  }
}
```

#### `notification.created`

Создание уведомления.

```json
{
  "eventType": "notification.created",
  "payload": {
    "user_id": 1,
    "type": "grade.created",
    "payload": { ... }
  }
}
```

#### `chat.message.created`

Новое сообщение в чате.

```json
{
  "eventType": "chat.message.created",
  "payload": {
    "thread_id": 1,
    "message_id": 1,
    "user_id": 1,
    "content": "..."
  }
}
```

### Event Channels

События доставляются в каналы:

- `user.{user_id}` - события для конкретного пользователя
- `chat.{thread_id}` - события чата
- `schedule` - события расписания
- `global` - глобальные события

### Replay

#### GET `/api/realtime/replay`

Получение событий из event_store для replay.

**Query Parameters:**
- `channel`: Канал (например, `user.1`)
- `from`: Время начала (timestamp)
- `to`: Время окончания (timestamp)
- `limit`: Максимум событий

## Rate Limiting

### Лимиты

- **Login**: 5/min per IP + 10/min per username
- **File Presign**: 30/min per user
- **Chat Messages**: 20/min per user
- **Assignment Submit**: 10/min per user
- **API (общий)**: 60/min per IP

### Ответ при превышении

```json
{
  "message": "Too Many Attempts.",
  "retry_after": 60
}
```

HTTP Status: `429 Too Many Requests`

## Ошибки

### Формат ошибки

```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error
- `503` - Service Unavailable (read-only mode)

## Pagination

Для списков используется пагинация:

**Query Parameters:**
- `page`: Номер страницы (default: 1)
- `per_page`: Элементов на странице (default: 15, max: 100)

**Response:**
```json
{
  "data": [ ... ],
  "current_page": 1,
  "per_page": 15,
  "total": 100,
  "last_page": 7
}
```

## Фильтрация и сортировка

### Фильтрация

Используется через query parameters:

```
GET /api/v1/assignments?group_id=1&subject_id=2&status=active
```

### Сортировка

```
GET /api/v1/assignments?sort=created_at&order=desc
```

## Webhooks

### Создание Webhook

```http
POST /api/admin/webhooks
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Schedule Webhook",
  "url": "https://example.com/webhook",
  "secret": "webhook-secret",
  "enabled": true,
  "event_types": ["schedule.changed", "grade.created"]
}
```

### Доставка Webhook

Webhooks доставляются асинхронно через `DeliverWebhooks` job.

**Формат payload:**
```json
{
  "eventId": 1,
  "eventType": "schedule.changed",
  "actorUserId": 123,
  "entityType": "ScheduleItem",
  "entityId": 456,
  "payload": { ... },
  "createdAt": "2024-01-01T12:00:00Z"
}
```

**Подпись:**
```
X-Signature: sha256=HMAC_SHA256(body, secret)
```

## Примеры использования

### Получение расписания группы

```bash
curl -X GET "http://localhost:8000/api/v1/schedule?view=group&id=1&from=2024-01-01&to=2024-01-31" \
  -H "Authorization: Bearer {token}"
```

### Создание оценки

```bash
curl -X POST "http://localhost:8000/api/v1/journal/grades" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "lesson_id": 1,
    "student_id": 1,
    "value": 5,
    "type": "exam"
  }'
```

### Печать расписания

```bash
curl -X GET "http://localhost:8000/api/print/schedule?view=group&id=1&from=2024-01-01&to=2024-01-31&format=pdf" \
  -H "Authorization: Bearer {token}" \
  --output schedule.pdf
```

