# K6 Load Testing

Нагрузочное тестирование с помощью k6.

## Установка

```bash
# macOS
brew install k6

# Linux
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6

# Windows
choco install k6
```

## Запуск тестов

### Базовый нагрузочный тест

```bash
k6 run load-test.js
```

### Тест медленных запросов

```bash
k6 run slow-queries-test.js
```

### С переменными окружения

```bash
API_URL=http://localhost/api USER_EMAIL=test@example.com USER_PASSWORD=password k6 run load-test.js
```

## Профилирование

### Включить профилирование в Laravel

Добавить в `.env`:
```
APP_DEBUG=true
LOG_LEVEL=debug
```

### Мониторинг медленных запросов

```sql
-- Включить логирование медленных запросов в PostgreSQL
ALTER SYSTEM SET log_min_duration_statement = 1000; -- логировать запросы > 1s
SELECT pg_reload_conf();
```

### Проверка индексов

```sql
-- Найти таблицы без индексов
SELECT schemaname, tablename 
FROM pg_tables 
WHERE schemaname = 'public' 
AND tablename NOT IN (
    SELECT DISTINCT tablename 
    FROM pg_indexes 
    WHERE schemaname = 'public'
);

-- Найти неиспользуемые индексы
SELECT schemaname, tablename, indexname, idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY schemaname, tablename;
```

## Метрики

- `http_req_duration`: Время ответа запросов
- `http_req_failed`: Процент неудачных запросов
- `errors`: Кастомная метрика ошибок

## Пороги (Thresholds)

- 95% запросов должны быть < 500ms
- 99% запросов должны быть < 2000ms
- Процент ошибок < 10%

