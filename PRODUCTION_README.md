# Production Deployment Guide

## Multi-Tenant Setup

1. Создать tenants через API или seeder:
```bash
php artisan db:seed --class=TenantSeeder
```

2. Настроить домены для каждого tenant или использовать заголовки:
- `X-Tenant-ID`: ID tenant
- `X-Tenant-Slug`: slug tenant
- Или subdomain: `tenant1.example.com`

## Security

### 2FA для админов
```bash
# Включить 2FA для пользователя
POST /api/v1/auth/2fa/enable
# После логина проверить код
POST /api/v1/auth/2fa/verify
```

### Rate Limiting
Настроено: 60 запросов/минуту на IP/user

### Security Headers
Автоматически добавляются:
- CSP
- X-Content-Type-Options
- X-Frame-Options
- HSTS (в production)

## Backups

### PostgreSQL
```bash
# Автоматически (ежедневно в 02:00)
php artisan backup:database

# Вручную
php artisan backup:database

# Восстановление
php artisan backup:restore storage/app/backups/backup_YYYYMMDD_HHMMSS.sql.gz
```

### MinIO
```bash
# Использовать скрипт
./backend/database/scripts/backup_minio.sh /backups/minio
```

## Observability

### Metrics
- Endpoint: `/api/metrics` (Prometheus format)
- Health check: `/api/health`

### Logs
- Application: `storage/logs/laravel.log`
- Slow queries: `storage/logs/slow.log` (>=300ms)

## Read-Only Mode

Включить через settings:
```sql
INSERT INTO settings (key, value, readonly_mode) 
VALUES ('system.readonly_mode', 'true', false);
```

## CI/CD

GitHub Actions настроен:
- Тесты на push/PR
- Автоматический deploy на main branch

## Миграции

```bash
# Применение
php artisan migrate

# Откат с бэкапом
php artisan migrate:rollback-safe
```

## Secrets Management

```php
$secrets = app(\App\Services\Secrets\SecretsManager::class);
$secrets->store('api_key', 'value', $tenantId);
$value = $secrets->get('api_key', $tenantId);
```

## LDAP (опционально)

1. Создать LDAP config через API
2. Установить `LDAP_ENABLED=true` в `.env`
3. Использовать LDAP credentials при логине

## Печатные формы

Endpoints:
- `/api/v1/reports/journal`
- `/api/v1/reports/schedule?type=group|room|teacher`
- `/api/v1/reports/grade-sheet`
- `/api/v1/reports/order`

Все возвращают PDF файлы.


