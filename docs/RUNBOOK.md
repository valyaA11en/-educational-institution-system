# Runbook - Операционные процедуры

## Развертывание (Deployment)

### Подготовка

1. **Проверка окружения**:
   ```bash
   # Проверить обязательные переменные
   backend/.env должен содержать:
   - APP_KEY
   - JWT_SECRET
   - DB_*
   - REDIS_*
   - AWS_*
   ```

2. **Зависимости**:
   ```bash
   cd backend
   composer install --no-dev --optimize-autoloader
   
   cd ../frontend
   npm ci
   npm run build
   ```

### Процесс развертывания

1. **Backup текущей версии**:
   ```bash
   # Создать backup перед деплоем
   docker exec pdo_backup /usr/local/bin/backup-all.sh
   ```

2. **Обновление кода**:
   ```bash
   git pull origin main
   ```

3. **Миграции**:
   ```bash
   # Проверить статус миграций
   docker exec pdo_php_fpm php artisan migrate:status
   
   # Запустить миграции
   docker exec pdo_php_fpm php artisan migrate --force
   ```

4. **Очистка кеша**:
   ```bash
   docker exec pdo_php_fpm php artisan config:clear
   docker exec pdo_php_fpm php artisan cache:clear
   docker exec pdo_php_fpm php artisan route:clear
   docker exec pdo_php_fpm php artisan view:clear
   ```

5. **Перезапуск сервисов**:
   ```bash
   docker-compose restart php-fpm
   docker-compose restart queue-worker
   ```

6. **Smoke Tests**:
   ```bash
   ./scripts/smoke-test.sh
   ```

### Rollback

Если что-то пошло не так:

1. **Откат миграций**:
   ```bash
   docker exec pdo_php_fpm php artisan migrate:rollback --step=1
   ```

2. **Восстановление кода**:
   ```bash
   git checkout <previous-commit>
   docker-compose restart php-fpm
   ```

3. **Восстановление БД** (если необходимо):
   ```bash
   # См. docs/DR.md
   ```

## Миграции базы данных

### Проверка перед миграцией

```bash
# Просмотр статуса
docker exec pdo_php_fpm php artisan migrate:status

# Просмотр pending миграций
docker exec pdo_php_fpm php artisan migrate --pretend
```

### Запуск миграций

```bash
# Production
docker exec pdo_php_fpm php artisan migrate --force

# С транзакцией (PostgreSQL)
docker exec pdo_php_fpm php artisan migrate --force --database=pgsql
```

### Откат миграций

```bash
# Откат последней миграции
docker exec pdo_php_fpm php artisan migrate:rollback --step=1

# Откат всех миграций (ОПАСНО!)
docker exec pdo_php_fpm php artisan migrate:reset
```

### Создание миграций

```bash
# Создать миграцию
docker exec pdo_php_fpm php artisan make:migration create_example_table

# Создать миграцию с моделью
docker exec pdo_php_fpm php artisan make:model Example -m
```

## Мониторинг

### Prometheus Metrics

**Доступ**: http://localhost:9090 (dev) или http://prometheus:9090

**Эндпоинт метрик**: `/api/metrics`

**Ключевые метрики**:
- `http_requests_total` - общее количество запросов
- `http_request_duration_seconds` - длительность запросов (p95, p50)
- `db_queries_total` - количество запросов к БД
- `active_users` - активные пользователи

### Grafana Dashboards

**Доступ**: http://localhost:3001 (dev)

**Dashboard**: "PDO System Overview"

**Ключевые панели**:
- Request Rate (запросов/сек)
- Request Duration (p95 latency)
- Error Rate (4xx, 5xx)
- Database Queries
- Active Users

### Health Checks

```bash
# Проверка health endpoint
curl http://localhost:8000/api/health

# Ожидаемый ответ:
{
  "status": "ok",
  "timestamp": "2024-01-01T12:00:00Z",
  "database": true,
  "redis": true
}
```

### Логи

```bash
# Laravel логи
docker logs -f pdo_php_fpm

# Nginx логи
docker logs -f pdo_nginx

# Queue worker логи
docker logs -f pdo_queue_worker

# Медленные запросы
tail -f backend/storage/logs/slow.log
```

## Инциденты

### Типы инцидентов

1. **Сервис недоступен** (503)
2. **Высокая нагрузка** (медленные ответы)
3. **Ошибки БД** (500, connection errors)
4. **Проблемы с очередями** (накопление jobs)
5. **Проблемы с файлами** (MinIO недоступен)

### Процедуры реагирования

#### 1. Сервис недоступен

**Симптомы**: 503 ошибки, health check fails

**Действия**:
```bash
# 1. Проверить статус контейнеров
docker ps

# 2. Проверить логи
docker logs pdo_php_fpm --tail 100
docker logs pdo_nginx --tail 100

# 3. Проверить БД
docker exec pdo_postgres pg_isready

# 4. Проверить Redis
docker exec pdo_redis redis-cli ping

# 5. Перезапустить сервисы
docker-compose restart php-fpm nginx
```

#### 2. Высокая нагрузка

**Симптомы**: p95 latency > 1s, медленные ответы

**Действия**:
```bash
# 1. Проверить метрики в Grafana
# 2. Проверить медленные запросы
tail -f backend/storage/logs/slow.log

# 3. Проверить активные соединения к БД
docker exec pdo_postgres psql -U laravel -c "SELECT count(*) FROM pg_stat_activity;"

# 4. Проверить queue workers
docker logs pdo_queue_worker --tail 50

# 5. Масштабировать (если возможно)
docker-compose up -d --scale queue-worker=3
```

#### 3. Ошибки БД

**Симптомы**: 500 ошибки, connection errors

**Действия**:
```bash
# 1. Проверить статус PostgreSQL
docker exec pdo_postgres pg_isready

# 2. Проверить логи PostgreSQL
docker logs pdo_postgres --tail 100

# 3. Проверить дисковое пространство
df -h

# 4. Проверить активные транзакции
docker exec pdo_postgres psql -U laravel -c "SELECT * FROM pg_stat_activity WHERE state = 'active';"

# 5. Если необходимо, перезапустить PostgreSQL
docker-compose restart postgres
```

#### 4. Проблемы с очередями

**Симптомы**: Накопление jobs, медленная обработка

**Действия**:
```bash
# 1. Проверить количество failed jobs
docker exec pdo_php_fpm php artisan queue:failed

# 2. Проверить статус queue worker
docker logs pdo_queue_worker --tail 50

# 3. Перезапустить queue worker
docker-compose restart queue-worker

# 4. Очистить failed jobs (если необходимо)
docker exec pdo_php_fpm php artisan queue:flush

# 5. Масштабировать workers
docker-compose up -d --scale queue-worker=3
```

#### 5. Проблемы с файлами

**Симптомы**: Ошибки загрузки файлов, MinIO недоступен

**Действия**:
```bash
# 1. Проверить статус MinIO
docker logs pdo_minio --tail 50

# 2. Проверить доступность MinIO
curl http://localhost:9000/minio/health/live

# 3. Перезапустить MinIO
docker-compose restart minio

# 4. Проверить дисковое пространство
df -h
```

### Эскалация

Если проблема не решается:

1. **Документировать**: Записать симптомы, время, действия
2. **Уведомить**: Связаться с командой разработки
3. **Rollback**: Рассмотреть откат к предыдущей версии
4. **Backup**: Убедиться, что backups актуальны

## Обслуживание

### Регулярные задачи

**Ежедневно**:
- Проверка backups (автоматически)
- Проверка метрик в Grafana
- Проверка логов на ошибки

**Еженедельно**:
- Проверка дискового пространства
- Проверка failed jobs
- Обзор audit log на подозрительную активность

**Ежемесячно**:
- Обновление зависимостей (composer, npm)
- Проверка безопасности (dependencies audit)
- Обзор производительности

### Очистка

```bash
# Очистка старых логов (старше 30 дней)
find backend/storage/logs -name "*.log" -mtime +30 -delete

# Очистка старых backups (старше 14 дней - автоматически)
# Проверяется в backup скриптах

# Очистка кеша
docker exec pdo_php_fpm php artisan cache:clear
docker exec pdo_redis redis-cli FLUSHDB
```

## Чеклист релиза

Перед каждым релизом необходимо выполнить следующие проверки:

### Pre-Release

- [ ] **Код готов**: Все изменения закоммичены и запушены
- [ ] **CI Pipeline**: Все тесты проходят (backend, frontend, smoke tests)
- [ ] **Code Review**: Код проверен и одобрен
- [ ] **Документация**: Обновлена при необходимости
- [ ] **Миграции**: Все миграции протестированы в staging

### Deployment

- [ ] **Backup**: Создан backup текущей версии
  ```bash
  docker exec pdo_backup /usr/local/bin/backup-all.sh
  ```
- [ ] **Environment**: Проверены все обязательные env переменные
- [ ] **Миграции**: Запущены миграции
  ```bash
  docker exec pdo_php_fpm php artisan migrate:status
  docker exec pdo_php_fpm php artisan migrate --force
  ```
- [ ] **Кеш**: Очищен кеш
  ```bash
  docker exec pdo_php_fpm php artisan config:clear
  docker exec pdo_php_fpm php artisan cache:clear
  ```
- [ ] **Сервисы**: Перезапущены сервисы
  ```bash
  docker-compose restart php-fpm queue-worker
  ```

### Post-Release Acceptance Tests

#### 1. Multi-Tenant

- [ ] **Создание tenants**: Созданы два тестовых tenant через `/api/admin/tenants`
- [ ] **Изоляция данных**: 
  - Создана группа в tenant 1
  - Переключение на tenant 2
  - Группа из tenant 1 не видна в tenant 2
- [ ] **Переключение tenant**: 
  - Админ может переключить tenant через `/api/admin/tenants/{id}/switch`
  - Новый токен содержит правильный `tenant_id`
  - Данные фильтруются по новому tenant

#### 2. Read-Only Mode

- [ ] **Включение режима**: 
  ```bash
  docker exec pdo_php_fpm php artisan tinker
  # DB::table('settings')->updateOrInsert(
  #   ['key' => 'system.read_only'],
  #   ['value' => 'true', 'updated_at' => now()]
  # );
  ```
- [ ] **Проверка блокировки**: 
  - POST/PUT/DELETE запросы возвращают 503 с сообщением "System in read-only mode"
  - GET запросы работают нормально
- [ ] **Frontend баннер**: 
  - На фронтенде отображается баннер о read-only режиме
  - Баннер виден на всех страницах

#### 3. 2FA

- [ ] **Настройка 2FA**: 
  - Админ заходит в `/settings/security`
  - Нажимает "Настроить 2FA"
  - Сканирует QR-код в приложении-аутентификаторе
- [ ] **Активация**: 
  - Вводит код из приложения
  - 2FA активирована, сохранены recovery codes
- [ ] **Логин с 2FA**: 
  - Выходит из системы
  - Логинится с email/password
  - Получает `requires_2fa: true` и `temp_token`
  - Вводит код из приложения
  - Получает access/refresh токены

#### 4. Backups

- [ ] **Backup контейнер**: 
  ```bash
  docker ps | grep pdo_backup
  docker logs pdo_backup --tail 50
  ```
- [ ] **Создание backup**: 
  ```bash
  docker exec pdo_backup /usr/local/bin/backup-all.sh
  ls -lh /backups/postgres/
  ls -lh /backups/minio/
  ```
- [ ] **Restore check**: 
  ```bash
  ./infra/scripts/restore_check.sh
  ```
  - Скрипт проходит успешно
  - Проверены таблицы: tenants, users, groups

#### 5. Print (PDF)

- [ ] **Расписание PDF**: 
  ```bash
  curl -X GET "http://localhost:8000/api/print/schedule?view=group&id=1&from=2024-01-01&to=2024-01-31&format=pdf" \
    -H "Authorization: Bearer {token}" \
    --output schedule.pdf
  ```
  - Файл скачивается
  - Содержит расписание группы
  - Включает шапку с tenant name
- [ ] **Журнал PDF**: 
  ```bash
  curl -X GET "http://localhost:8000/api/print/journal?groupId=1&format=pdf" \
    -H "Authorization: Bearer {token}" \
    --output journal.pdf
  ```
  - Файл скачивается
  - Содержит журнал с оценками
  - Включает подписи

#### 6. Metrics

- [ ] **Prometheus scraping**: 
  ```bash
  curl http://localhost:8000/api/metrics
  ```
  - Возвращает метрики в Prometheus формате
  - Содержит `http_requests_total`, `http_request_duration_seconds`
- [ ] **Grafana Dashboard**: 
  - Открыть http://localhost:3001
  - Перейти в "PDO System Overview"
  - Видны графики:
    - Request Rate
    - Request Duration (p95)
    - Error Rate
    - Database Queries

#### 7. CI Pipeline

- [ ] **GitHub Actions**: 
  - Перейти в GitHub Actions
  - Проверить последний workflow run
  - Все jobs зеленые:
    - Backend Tests & Lint
    - Frontend Tests & Lint
    - Smoke Tests
  - Нет ошибок в логах

### Smoke Tests

После деплоя запустить smoke tests:

```bash
./scripts/smoke-test.sh
```

**Ожидаемый результат:**
- ✅ PostgreSQL готов
- ✅ Redis готов
- ✅ Миграции выполнены
- ✅ Seeders выполнены
- ✅ Laravel server запущен
- ✅ Health check passed

### Rollback Plan

Если acceptance tests не проходят:

1. **Остановить деплой**: Не продолжать если есть критические ошибки
2. **Откат миграций**: 
   ```bash
   docker exec pdo_php_fpm php artisan migrate:rollback --step=1
   ```
3. **Откат кода**: 
   ```bash
   git checkout <previous-tag>
   docker-compose restart php-fpm
   ```
4. **Восстановление БД** (если необходимо): См. `docs/DR.md`

### Post-Release Monitoring

В течение 24 часов после релиза:

- [ ] **Метрики**: Проверить Grafana на аномалии
- [ ] **Логи**: Проверить логи на ошибки
- [ ] **Health**: Проверить `/api/health` endpoint
- [ ] **Пользователи**: Собрать feedback от пользователей

## Контакты

- **On-call**: [указать контакты]
- **Slack**: [указать канал]
- **Email**: [указать email]

