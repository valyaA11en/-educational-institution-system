# K6 Load Testing

Нагрузочное тестирование API с помощью k6.

## Скрипты

- `smoke.js` - Базовый smoke test: логин, /api/auth/me, /api/notifications
- `schedule.js` - Тестирование расписания: чтение, создание item, проверка конфликтов, force save
- `chat.js` - Тестирование чата: открытие чата, отправка сообщений
- `assignments.js` - Тестирование заданий: presign, submit (TODO: реальный PUT файла)
- `documents.js` - Тестирование документов: список, экспорт DOCX (если доступно)

## Запуск

### Локально (требуется установленный k6)

```bash
# Smoke test
k6 run --out json=results/smoke.json smoke.js

# Schedule test
k6 run --out json=results/schedule.json schedule.js

# Chat test
k6 run --out json=results/chat.json chat.js

# Assignments test
k6 run --out json=results/assignments.json assignments.js

# Documents test
k6 run --out json=results/documents.json documents.js
```

### С переменными окружения

```bash
k6 run \
  -e BASE_URL=http://localhost:8000 \
  -e EMAIL=admin@example.com \
  -e PASSWORD=password \
  --out json=results/smoke.json \
  smoke.js
```

### Через Docker Compose

```bash
docker compose --profile loadtest up k6-smoke
docker compose --profile loadtest up k6-schedule
# и т.д.
```

## Отчёты

После запуска результаты сохраняются в `results/*.json`. Для генерации отчётов с p95 latency и error rate используйте:

```bash
# Генерация отчёта
node generate-report.js results/smoke.json
```

Или используйте встроенные метрики k6:

```bash
k6 run --summary-export=results/summary.json smoke.js
```

## Метрики

Все скрипты отслеживают:
- **p95 latency** - 95-й перцентиль времени ответа
- **Error rate** - Процент ошибок
- **HTTP request duration** - Длительность HTTP запросов

Пороги (thresholds) настроены для каждого скрипта индивидуально.


