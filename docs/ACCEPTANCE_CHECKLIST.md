# Acceptance Checklist

Данный документ содержит чеклисты для проверки функциональности системы перед релизом.

## 1. Multi-Tenant

### Создание и изоляция данных

- [ ] **Создать два tenant**:
  ```bash
  # POST /api/admin/tenants
  {
    "name": "School 1",
    "slug": "school1",
    "timezone": "Europe/Amsterdam"
  }
  {
    "name": "School 2", 
    "slug": "school2",
    "timezone": "Europe/Amsterdam"
  }
  ```

- [ ] **Создать данные в tenant 1**:
  - Войти как admin в tenant 1
  - Создать группу "Group A" в tenant 1
  - Создать предмет "Math" в tenant 1

- [ ] **Проверить изоляцию**:
  - Переключиться на tenant 2 (через `/api/admin/tenants/2/switch`)
  - Проверить список групп: "Group A" не должна быть видна
  - Проверить список предметов: "Math" не должен быть виден

- [ ] **Переключение tenant**:
  - Админ может переключить tenant через UI (tenant selector в topbar)
  - После переключения новый токен содержит правильный `tenant_id`
  - Все последующие запросы фильтруются по новому tenant

**Ожидаемый результат**: Данные полностью изолированы между tenant, переключение работает корректно.

## 2. Read-Only Mode

### Включение режима

- [ ] **Включить read-only через setting**:
  ```bash
  docker exec pdo_php_fpm php artisan tinker
  # DB::table('settings')->updateOrInsert(
  #   ['key' => 'system.read_only'],
  #   ['value' => 'true', 'updated_at' => now()]
  # );
  ```

- [ ] **Проверить блокировку записей**:
  ```bash
  # POST запрос должен вернуть 503
  curl -X POST "http://localhost:8000/api/v1/directory/groups" \
    -H "Authorization: Bearer {token}" \
    -H "Content-Type: application/json" \
    -d '{"name": "Test Group"}'
  
  # Ожидаемый ответ:
  {
    "message": "System in read-only mode",
    "read_only": true
  }
  ```

- [ ] **Проверить чтение**:
  ```bash
  # GET запросы должны работать
  curl -X GET "http://localhost:8000/api/v1/directory/groups" \
    -H "Authorization: Bearer {token}"
  ```

- [ ] **Проверить frontend баннер**:
  - Открыть приложение в браузере
  - Должен отображаться баннер о read-only режиме
  - Баннер виден на всех страницах
  - Кнопки создания/редактирования должны быть disabled

**Ожидаемый результат**: Все записи заблокированы, чтение работает, frontend показывает баннер.

## 3. 2FA (Two-Factor Authentication)

### Настройка 2FA для админа

- [ ] **Setup 2FA**:
  - Войти как admin
  - Перейти в `/settings/security`
  - Нажать "Настроить 2FA"
  - Получить QR-код и recovery codes
  - Сохранить recovery codes в безопасном месте

- [ ] **Активация 2FA**:
  - Открыть приложение-аутентификатор (Google Authenticator, Authy)
  - Сканировать QR-код
  - Ввести код из приложения
  - Нажать "Активировать 2FA"
  - **Ожидаемый результат**: 2FA активирована, статус показывает "2FA включена"

- [ ] **Логин с 2FA**:
  - Выйти из системы
  - Войти с email/password
  - **Ожидаемый результат**: Получен ответ с `requires_2fa: true` и `temp_token`
  - Ввести код из приложения-аутентификатора
  - **Ожидаемый результат**: Получены access/refresh токены, вход выполнен

- [ ] **Recovery codes**:
  - Выйти из системы
  - Войти с email/password
  - Вместо кода из приложения использовать recovery code
  - **Ожидаемый результат**: Вход выполнен успешно

**Ожидаемый результат**: Админ может настроить и использовать 2FA для входа.

## 4. Backups

### Проверка backup системы

- [ ] **Backup контейнер запущен**:
  ```bash
  docker ps | grep pdo_backup
  # Должен быть запущен
  ```

- [ ] **Ручной backup**:
  ```bash
  docker exec pdo_backup /usr/local/bin/backup-all.sh
  ```

- [ ] **Проверить создание backup**:
  ```bash
  # PostgreSQL backup
  ls -lh /backups/postgres/ | tail -5
  # Должен быть файл YYYY-MM-DD.sql.gz
  
  # MinIO backup
  ls -lh /backups/minio/ | tail -5
  # Должна быть директория YYYY-MM-DD/
  ```

- [ ] **Restore check**:
  ```bash
  ./infra/scripts/restore_check.sh
  ```
  **Ожидаемый результат**:
  ```
  ✓ PostgreSQL is ready
  ✓ Restored database
  ✓ tenants table exists with X record(s)
  ✓ users table exists with X record(s)
  ✓ Backup verification passed
  ```

**Ожидаемый результат**: Backups создаются, restore check проходит успешно.

## 5. Print (PDF)

### Генерация PDF отчетов

- [ ] **Расписание PDF**:
  ```bash
  curl -X GET "http://localhost:8000/api/print/schedule?view=group&id=1&from=2024-01-01&to=2024-01-31&format=pdf" \
    -H "Authorization: Bearer {token}" \
    --output schedule.pdf
  ```
  **Проверить**:
  - Файл скачивается
  - Открывается в PDF viewer
  - Содержит расписание группы
  - Включает шапку с tenant name
  - Включает дату печати
  - Включает подписи

- [ ] **Журнал PDF**:
  ```bash
  curl -X GET "http://localhost:8000/api/print/journal?groupId=1&format=pdf" \
    -H "Authorization: Bearer {token}" \
    --output journal.pdf
  ```
  **Проверить**:
  - Файл скачивается
  - Содержит журнал с оценками
  - Включает список студентов
  - Включает оценки по урокам
  - Включает подписи

- [ ] **Посещаемость PDF**:
  ```bash
  curl -X GET "http://localhost:8000/api/print/attendance?groupId=1&from=2024-01-01&to=2024-01-31&format=pdf" \
    -H "Authorization: Bearer {token}" \
    --output attendance.pdf
  ```
  **Проверить**:
  - Файл скачивается
  - Содержит посещаемость студентов
  - Включает даты и статусы посещения

**Ожидаемый результат**: Все PDF отчеты генерируются и скачиваются корректно.

## 6. Metrics

### Prometheus и Grafana

- [ ] **Prometheus scraping**:
  ```bash
  curl http://localhost:8000/api/metrics
  ```
  **Проверить**:
  - Возвращает метрики в Prometheus формате (text/plain)
  - Содержит `http_requests_total`
  - Содержит `http_request_duration_seconds_bucket`
  - Содержит `db_queries_total`

- [ ] **Prometheus UI**:
  - Открыть http://localhost:9090
  - Перейти в Status → Targets
  - Проверить что `pdo-api` target UP
  - Выполнить запрос: `rate(http_requests_total[5m])`
  - Должны быть данные

- [ ] **Grafana Dashboard**:
  - Открыть http://localhost:3001
  - Войти (admin/admin)
  - Перейти в Dashboards → PDO System Overview
  - **Проверить панели**:
    - Request Rate показывает запросы/сек
    - Request Duration (p95) показывает latency
    - Error Rate показывает ошибки
    - Database Queries показывает запросы к БД
  - Сгенерировать несколько запросов к API
  - Проверить что метрики обновляются

**Ожидаемый результат**: Prometheus собирает метрики, Grafana dashboard отображает данные.

## 7. CI Pipeline

### GitHub Actions

- [ ] **Проверить последний workflow run**:
  - Перейти в GitHub → Actions
  - Найти последний workflow run для ветки main/develop
  - **Проверить jobs**:
    - ✅ Backend Tests & Lint (зеленый)
    - ✅ Frontend Tests & Lint (зеленый)
    - ✅ Smoke Tests (зеленый)
    - ✅ Docker Build (опционально, если был запущен)

- [ ] **Проверить логи**:
  - Backend: PHPUnit тесты прошли
  - Backend: Pint не нашел ошибок
  - Frontend: TypeScript typecheck прошел
  - Frontend: ESLint не нашел ошибок
  - Frontend: Build успешен
  - Smoke Tests: Все проверки прошли

- [ ] **Проверить артефакты** (если есть):
  - Test reports
  - Coverage reports

**Ожидаемый результат**: Все jobs в CI pipeline зеленые, нет ошибок.

## Общий чеклист

После выполнения всех acceptance тестов:

- [ ] Все тесты пройдены
- [ ] Нет критических ошибок в логах
- [ ] Метрики в норме (нет аномалий)
- [ ] Health check возвращает OK
- [ ] Пользователи могут работать с системой

## Быстрый тест

Для быстрой проверки всех функций:

```bash
# 1. Health check
curl http://localhost:8000/api/health

# 2. Metrics
curl http://localhost:8000/api/metrics | head -20

# 3. Smoke test
./scripts/smoke-test.sh

# 4. Проверить CI
# Открыть GitHub Actions в браузере
```

