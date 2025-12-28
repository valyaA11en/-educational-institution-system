# Архитектура системы PDO

## Обзор

PDO (Pedagogical Data Organization) - это монорепозиторий для управления образовательными процессами, построенный на Laravel 11 (backend) и Vue 3 (frontend) с поддержкой multi-tenancy.

## Структура проекта

```
.
├── backend/          # Laravel 11 API
│   ├── app/
│   │   ├── Domains/  # Доменные модули
│   │   ├── Models/   # Eloquent модели
│   │   ├── Services/ # Бизнес-логика
│   │   └── ...
│   └── ...
├── frontend/         # Vue 3 SPA
├── infra/            # Docker Compose, Nginx
└── docs/             # Документация
```

## Доменные модули

Система организована по доменам (Domain-Driven Design):

### 1. **Auth** (Аутентификация)
- JWT токены (access + refresh)
- 2FA (TOTP) для администраторов
- Device sessions
- Rate limiting и account lockout

### 2. **RBAC** (Роли и права)
- Роли: admin, методист, преподаватель, студент
- Permissions: гранулярные права доступа
- Policies: объектные права (Laravel Policies)

### 3. **Multi-Tenant** (Мультитенантность)
- Изоляция данных по `tenant_id`
- Tenant context через subdomain/header/query
- Глобальный scope `TenantScope` для всех моделей

### 4. **Directory** (Справочники)
- Groups (группы)
- Subgroups (подгруппы)
- Subjects (предметы)
- Rooms (кабинеты)
- TimeSlots (временные слоты)

### 5. **Schedule** (Расписание)
- Версионирование расписаний (draft → published → archived)
- Конфликты расписания
- Замены (replacements)

### 6. **Journal** (Журнал)
- Lessons (уроки)
- Grades (оценки)
- Attendance (посещаемость)
- GradePeriodSummaries (итоги периода)

### 7. **Assignments** (Задания)
- Assignments (задания)
- Submissions (решения)
- Files (файлы через MinIO/S3)

### 8. **Documents** (Документы)
- Document workflow (создание → согласование → подписание)
- Templates (шаблоны)
- Routes (маршруты согласования)
- GOST-совместимая печать

### 9. **Notifications** (Уведомления)
- In-app уведомления
- Push-уведомления (WebPush)
- WebSocket realtime

### 10. **Chat** (Чат)
- Threads (темы)
- Messages (сообщения)
- Moderation (модерация)
- Reports (жалобы)

### 11. **Rules** (Правила)
- Rule engine для автоматизации
- Event-driven триггеры
- Risk management

### 12. **Tickets** (Тикеты)
- Support tickets
- Ticket messages

### 13. **Contests** (Конкурсы)
- Contests
- Rubrics (рубрики)
- Jury (жюри)
- Certificates (сертификаты)

### 14. **Exams** (Экзамены)
- Exam registrations
- Admission calculations
- Results

## Событийная архитектура

### Outbox Pattern

Система использует **Transactional Outbox Pattern** для гарантированной доставки событий:

1. **Создание события**: При изменении данных создается запись в `outbox_events` в той же транзакции
2. **Обработка**: Job `DispatchOutboxEvents` обрабатывает события каждую минуту
3. **Доставка**: События доставляются через:
   - WebSocket (broadcast)
   - Event Store (для replay)
   - Webhooks (асинхронно)

### Структура OutboxEvent

```php
{
  "id": 1,
  "event_type": "schedule.changed",
  "actor_user_id": 123,
  "entity_type": "ScheduleItem",
  "entity_id": 456,
  "payload_json": { ... },
  "idempotency_key": "unique-key",
  "status": "sent", // new, processing, sent, failed
  "attempts": 0,
  "sent_at": "2024-01-01T12:00:00Z"
}
```

### Типы событий

Определены в `App\Support\Events\EventTypes`:

- `SCHEDULE_CHANGED` - изменение расписания
- `GRADE_CREATED` - создание оценки
- `ASSIGNMENT_CREATED` - создание задания
- `ASSIGNMENT_DUE_SOON` - приближается дедлайн
- `SUBMISSION_STATUS_CHANGED` - изменение статуса решения
- `DOCUMENT_STATUS_CHANGED` - изменение статуса документа
- `CHAT_MESSAGE_CREATED` - новое сообщение в чате
- `NOTIFICATION_CREATED` - создание уведомления

### Обработка событий

1. **Broadcast**: События транслируются через Laravel Broadcasting (Redis → WebSocket)
2. **Event Store**: Записываются в `event_store` для replay
3. **Rules**: Обрабатываются Rule Engine для автоматизации
4. **Webhooks**: Доставляются внешним системам

## Tenant Scope

### Глобальный Scope

Все доменные модели используют `HasTenant` trait, который автоматически:

1. **Фильтрация**: Применяет `TenantScope` для фильтрации по `tenant_id`
2. **Автозаполнение**: Устанавливает `tenant_id` при создании

```php
use App\Traits\HasTenant;

class Group extends Model
{
    use HasTenant;
    // Автоматически фильтруется по tenant_id
}
```

### Определение Tenant

Tenant определяется через middleware `IdentifyTenant`:

1. **Subdomain**: `tenant-slug.example.com`
2. **Header**: `X-Tenant: tenant-slug`
3. **Query** (dev only): `?tenant=tenant-slug`

### Обход Tenant Scope

```php
// Без tenant scope
Group::withoutTenant()->get();

// С конкретным tenant
Group::withTenant($tenantId)->get();
```

## Инфраструктура

### Backend
- **Laravel 11** (PHP 8.2)
- **PostgreSQL 16** (основная БД)
- **Redis** (cache, queue, WebSocket)
- **MinIO** (S3-совместимое хранилище)

### Frontend
- **Vue 3** (Composition API)
- **TypeScript**
- **Pinia** (state management)
- **Vuetify 3** (UI framework)
- **Vite** (build tool)

### Инфраструктура
- **Docker Compose** (dev environment)
- **Nginx** (reverse proxy)
- **Laravel Echo Server** (WebSocket)
- **Prometheus + Grafana** (мониторинг)

## Потоки данных

### Запрос → Ответ

```
Client → Nginx → Laravel (php-fpm) → PostgreSQL
                              ↓
                          Redis (cache)
                              ↓
                          MinIO (files)
```

### Realtime события

```
Laravel → Redis → Laravel Echo Server → WebSocket → Client
```

### Outbox события

```
Laravel → outbox_events (DB) → DispatchOutboxEvents (Job) → 
    ├→ Broadcast (WebSocket)
    ├→ Event Store
    ├→ Rules Engine
    └→ Webhooks
```

## Масштабирование

### Горизонтальное масштабирование

- **Stateless API**: Laravel не хранит состояние
- **Shared Redis**: Общий Redis для всех инстансов
- **Shared PostgreSQL**: Общая БД
- **Shared MinIO**: Общее хранилище

### Вертикальное масштабирование

- **Queue Workers**: Можно запускать несколько воркеров
- **WebSocket**: Laravel Echo Server поддерживает кластеризацию через Redis

## Безопасность

- **Multi-tenant изоляция**: Данные изолированы по tenant_id
- **RBAC**: Роли и права доступа
- **2FA**: Для администраторов
- **Rate Limiting**: Защита от злоупотреблений
- **CSP**: Content Security Policy
- **CORS**: Настроен для frontend доменов

## Мониторинг

- **Prometheus**: Метрики на `/api/metrics`
- **Grafana**: Dashboards для визуализации
- **Audit Log**: Логирование всех действий
- **Slow Query Log**: Логирование медленных запросов
