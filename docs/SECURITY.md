# Безопасность

## Обзор

PDO реализует многоуровневую систему безопасности для защиты данных и предотвращения несанкционированного доступа.

## Аутентификация

### JWT Tokens

- **Access Token**: Короткоживущий (60 минут), содержит `tenant_id` в claims
- **Refresh Token**: Долгоживущий (14 дней), используется для обновления access token
- **Token Refresh**: Автоматическое обновление при 401 ошибке
- **Refresh Endpoint Protection**: Проверка Origin/Referer для защиты от CSRF

### 2FA (Two-Factor Authentication)

**Требования:**
- Обязательно для ролей: admin, методист, руководство
- Настраивается через `EnforceTwoFactorForRoles` middleware

**Процесс:**
1. **Setup**: `POST /api/auth/2fa/setup` - генерирует QR-код и recovery codes
2. **Enable**: `POST /api/auth/2fa/enable {code}` - активирует 2FA
3. **Login**: Если 2FA включена, возвращается `temp_token`
4. **Verify**: `POST /api/auth/2fa/verify {tempToken, code}` - проверяет код и выдает токены

**Хранение:**
- TOTP secret: зашифрован в БД
- Recovery codes: JSON массив, зашифрован

### Device Sessions

- Отслеживание активных сессий
- Возможность отзыва сессий

## Авторизация (RBAC)

### Роли

- **admin**: Полный доступ
- **методист**: Управление учебным процессом
- **преподаватель**: Управление своими группами и предметами
- **студент**: Просмотр своих данных

### Permissions

Гранулярные права доступа:

```php
// Примеры permissions
'schedule.view'
'schedule.create'
'schedule.update'
'schedule.delete'
'grades.view'
'grades.create'
'assignments.submit'
'documents.approve'
```

### Policies

Laravel Policies для объектных прав:

```php
// Пример: DocumentPolicy
public function view(User $user, Document $document): bool
{
    // Пользователь может видеть документ если:
    // - он автор
    // - он в маршруте согласования
    // - у него есть право documents.view
}
```

### Middleware

- `auth:api` - проверка JWT токена
- `role:admin` - проверка роли
- `permission:schedule.view` - проверка права
- `object.permission:documents.view` - проверка объектного права

## Rate Limiting

### Настроенные лимиты

```php
// Login: 5/min per IP + 10/min per username
Route::post('auth/login')->middleware('throttle:login');

// File presign: 30/min per user
Route::post('presigned-upload')->middleware('throttle:file-presign');

// Chat messages: 20/min per user
Route::post('threads/{id}/messages')->middleware('throttle:chat-messages');

// Assignment submit: 10/min per user
Route::post('assignments/{id}/submit')->middleware('throttle:assignment-submit');
```

### Account Lockout

- **Максимум попыток**: 10 неудачных попыток
- **Время блокировки**: 15 минут
- **Отслеживание**: По email и IP адресу
- **Хранение**: Cache (быстро) + `auth_attempts` таблица (аудит)

## Security Headers

### HTTP Headers

Настроены в Nginx и Laravel middleware:

```
Content-Security-Policy: default-src 'self'; connect-src 'self' ws: wss:; img-src 'self' data:;
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
Permissions-Policy: geolocation=(), microphone=(), camera=(), ...
```

### CORS

Настроен в `config/cors.php`:

- **Allowed Origins**: Только frontend домены
- **Dev**: `localhost:3000`, `localhost:5173`, `localhost:8000`
- **Production**: Настраивается через `FRONTEND_URL`

## Multi-Tenant Изоляция

### Tenant Scope

Все доменные модели автоматически фильтруются по `tenant_id`:

```php
// Автоматически применяется WHERE tenant_id = ?
Group::all(); // Только группы текущего tenant

// Обход scope (только для админов)
Group::withoutTenant()->get();
```

### Tenant Identification

1. **Subdomain**: `tenant-slug.example.com`
2. **Header**: `X-Tenant: tenant-slug`
3. **Query** (dev): `?tenant=tenant-slug`

### Tenant Switching

Только администраторы могут переключать tenant:

```http
POST /api/admin/tenants/{id}/switch
Authorization: Bearer {token}
```

## Audit Logging

### Логируемые события

- Все изменения данных (CRUD операции)
- Аутентификация (login, logout, failed attempts)
- Изменения прав доступа
- Переключение tenant
- Критические операции

### Структура AuditLog

```php
{
  "user_id": 123,
  "action": "schedule.item.created",
  "entity": "ScheduleItem",
  "entity_id": 456,
  "before_json": { ... },
  "after_json": { ... },
  "ip": "192.168.1.1",
  "user_agent": "...",
  "created_at": "2024-01-01T12:00:00Z"
}
```

## Секреты

### Environment Variables

Обязательные переменные валидируются при старте:

- `APP_KEY` - ключ шифрования Laravel
- `JWT_SECRET` - секрет для JWT (минимум 32 символа)
- `DB_*` - параметры БД
- `REDIS_*` - параметры Redis
- `AWS_*` - параметры S3/MinIO

### Хранение секретов

- **Development**: `.env` файл (не коммитится)
- **Production**: Environment variables или secrets manager

## File Access Control

### MinIO/S3

- Файлы хранятся в S3-совместимом хранилище
- Presigned URLs для временного доступа
- Проверка прав доступа перед выдачей URL

### Загрузка файлов

1. Клиент запрашивает presigned URL
2. Сервер проверяет права (`can:assignments.submit`)
3. Выдается временный URL (15 минут)
4. Клиент загружает файл напрямую в S3

## SQL Injection Protection

- **Eloquent ORM**: Автоматическое экранирование
- **Prepared Statements**: Все запросы используют prepared statements
- **Query Builder**: Безопасное построение запросов

## XSS Protection

- **Vue 3**: Автоматическое экранирование в шаблонах
- **CSP**: Content Security Policy ограничивает выполнение скриптов
- **Sanitization**: Входные данные санитизируются

## CSRF Protection

- **JWT**: Не требует CSRF токенов (stateless)
- **Refresh Endpoint**: Защищен проверкой Origin/Referer
- **Web Forms**: Laravel CSRF для веб-форм (если есть)

## Безопасность API

### Валидация запросов

Все входные данные валидируются через Laravel Request classes:

```php
class CreateScheduleItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'group_id' => 'required|exists:groups,id',
            'subject_id' => 'required|exists:subjects,id',
            // ...
        ];
    }
}
```

### Идемпотентность

- Outbox events используют `idempotency_key`
- Webhook deliveries проверяют дубликаты

## Мониторинг безопасности

### Метрики

- Количество failed login attempts
- Rate limit hits
- 401/403 ошибки
- Account lockouts

### Алерты

- Множественные failed attempts с одного IP
- Подозрительная активность
- Необычные паттерны доступа

## Incident Response

### При обнаружении инцидента

1. **Изоляция**: Заблокировать подозрительные IP/пользователей
2. **Аудит**: Проверить audit_log на подозрительную активность
3. **Уведомление**: Уведомить администраторов
4. **Документация**: Задокументировать инцидент

### Процедуры

См. `docs/RUNBOOK.md` для детальных процедур реагирования на инциденты.

