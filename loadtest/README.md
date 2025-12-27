# Load Testing

Нагрузочное тестирование с помощью k6.

## Запуск через Docker Compose

```bash
# Запустить все тесты
docker compose --profile loadtest up

# Запустить конкретный тест
docker compose --profile loadtest up k6-smoke
docker compose --profile loadtest up k6-schedule
docker compose --profile loadtest up k6-chat
docker compose --profile loadtest up k6-assignments
docker compose --profile loadtest up k6-documents
```

## Переменные окружения

Настройте в `.env` или передайте через docker-compose:

```env
BASE_URL=http://backend:8000
TEST_EMAIL=admin@example.com
TEST_PASSWORD=password
```

## Результаты

Результаты сохраняются в `loadtest/results/*.json`.

Для генерации отчёта:

```bash
cd loadtest/k6
node generate-report.js ../results/smoke.json
```

## Метрики

Все скрипты отслеживают:
- **p95 latency** - 95-й перцентиль времени ответа
- **Error rate** - Процент ошибок
- **HTTP request duration** - Длительность HTTP запросов

Пороги (thresholds) настроены индивидуально для каждого скрипта.


