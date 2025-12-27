# Production Features

## 1. Multi-Tenant

**Миграции:**
- `tenants` table
- `tenant_id` добавлен во все основные таблицы
- `HasTenant` trait для автоматической изоляции

**Middleware:**
- `IdentifyTenant` - определение tenant по domain/subdomain/headers
- Автоматическая изоляция данных через global scope

**API:**
- `GET/POST/PATCH/DELETE /api/v1/admin/tenants`

## 2. SSO/LDAP + 2FA

**2FA:**
- `two_factor_auth` table
- `TwoFactorService` - генерация secret, QR codes, верификация
- `POST /api/v1/auth/2fa/setup` - настройка
- `POST /api/v1/auth/2fa/enable` - включение
- `POST /api/v1/auth/2fa/verify` - верификация кода
- `RequireTwoFactor` middleware для админов

**LDAP:**
- `ldap_configs` table
- `LdapService` - аутентификация через LDAP
- Конфигурация per-tenant

**Device Sessions:**
- `user_sessions` table
- Отслеживание активных сессий по устройствам

## 3. Печатные формы (PDF)

**Endpoints:**
- `GET /api/v1/reports/journal` - журнал успеваемости
- `GET /api/v1/reports/schedule?type=group|room|teacher` - расписание
- `GET /api/v1/reports/grade-sheet` - ведомость
- `GET /api/v1/reports/order` - приказы

**Views:**
- Blade templates в `resources/views/reports/`
- Использует `barryvdh/laravel-dompdf`

## 4. Security Hardening

**CSP Headers:**
- `SecurityHeaders` middleware
- Content-Security-Policy, X-Frame-Options, HSTS

**Rate Limiting:**
- 60 запросов/минуту на IP/user
- Настроено через Laravel throttle

**Audit Logging:**
- `AuditLog` middleware
- Логирование sensitive операций
- Tenant isolation в audit logs

**Secrets Management:**
- `SecretsManager` service
- Шифрование через Laravel Crypt
- Per-tenant secrets

**File Access Control:**
- `FileAccessControl` middleware
- Проверка tenant isolation для файлов

## 5. Observability

**Metrics:**
- `MetricsService` - Redis-based metrics
- `TrackMetrics` middleware - автоматический сбор
- `GET /api/metrics` - Prometheus format
- `GET /api/health` - health check

**Logging:**
- Slow queries (>=300ms) → `storage/logs/slow.log`
- Query performance monitoring (dev only)
- N+1 detection

**Tracing:**
- TODO: интеграция с Jaeger/OpenTelemetry

**Alerts:**
- Конфигурация в `config/observability.php`
- TODO: интеграция с Slack/Email

## 6. DR/Backups

**PostgreSQL:**
- `php artisan backup:database` - создание бэкапа
- `php artisan backup:restore {file}` - восстановление
- Автоматически ежедневно в 02:00
- Хранение 30 дней

**MinIO:**
- Скрипт `database/scripts/backup_minio.sh`
- Синхронизация bucket → архив

**Проверка:**
- `php artisan backup:check` - проверка актуальности бэкапов

**Read-Only Mode:**
- `ReadOnlyMode` middleware
- Включается через settings: `system.readonly_mode = true`
- Блокирует все запросы кроме GET/HEAD/OPTIONS

## 7. CI/CD

**GitHub Actions:**
- `.github/workflows/ci.yml` - тесты на push/PR
- `.github/workflows/deploy.yml` - deploy на main

**Миграции:**
- `php artisan migrate:rollback-safe` - откат с бэкапом

**Smoke Tests:**
- `php artisan smoke:test` - проверка после deploy
- Проверяет `/api/health` и `/api/metrics`

## Установка зависимостей

```bash
cd backend
composer require pragmarx/google2fa barryvdh/laravel-dompdf
```

## Настройка

1. **Multi-tenant:**
```bash
php artisan migrate
php artisan db:seed --class=TenantSeeder
```

2. **2FA:**
- Настроить через API endpoints
- Для админов рекомендуется обязательная 2FA

3. **Backups:**
- Настроить cron для `backup:database`
- Настроить MinIO backup скрипт

4. **Monitoring:**
- Настроить Prometheus scraping `/api/metrics`
- Настроить алерты в `config/observability.php`

## TODO

- [ ] LDAP интеграция в AuthController
- [ ] Полная реализация PDF reports (заполнение данными)
- [ ] Jaeger/OpenTelemetry tracing
- [ ] Slack/Email алерты
- [ ] Frontend для multi-tenant управления
- [ ] Frontend для 2FA настройки
- [ ] Frontend для reports


