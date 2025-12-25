# PDO Monorepo

Монорепозиторий для приложения на стеке:
- **Backend**: Laravel 11+ (PHP 8.2), PostgreSQL, Redis, MinIO, WebSocket, очередь (outbox + broadcaster)
- **Frontend**: Vue 3 + TypeScript + Pinia + Vue Router + Vuetify + Vite
- **Infra**: Docker Compose, Nginx, php-fpm, queue-worker, WebSocket, Vite dev server

## Структура

```bash
.
├── backend/        # Laravel backend
├── frontend/       # Vue 3 + TS + Vuetify frontend
├── infra/          # docker-compose, nginx и прочая инфраструктура
├── docs/           # архитектура, события, контракты
├── websocket/      # Laravel Echo Server / WebSocket сервис
├── docker-compose.yml
└── README.md
```

## Быстрый старт (dev)

```bash
# 1. Скопировать и настроить окружение
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env

# 2. Запустить инфраструктуру (из корня репо)
docker compose up -d --build

# 3. Выполнить миграции и базовую настройку Laravel
docker compose exec php-fpm php artisan migrate
docker compose exec php-fpm php artisan storage:link

# 4. Заполнить базу демо-данными (опционально)
docker compose exec php-fpm php artisan db:seed

# Или запустить конкретный сидер:
docker compose exec php-fpm php artisan db:seed --class=DemoDataSeeder
```

## Основные сервисы

- `nginx`      — входная точка, проксирует Vite dev server и Laravel API
- `php-fpm`    — Laravel backend
- `queue-worker` — обработчик очередей (Laravel queue, TODO: Horizon)
- `postgres`   — основная БД (bigint PK, snake_case таблицы)
- `redis`      — кеш, очереди, WS
- `minio`      — S3-совместимое хранилище
- `ws`         — WebSocket сервис (Laravel Echo Server)
- `frontend`   — Vite dev server для Vue 3

## Полезные команды

```bash
# Запуск всех сервисов
docker compose up -d --build

# Остановка
docker compose down

# Логи
docker compose logs -f

# Миграции
docker compose exec php-fpm php artisan migrate

# Очереди
docker compose exec queue-worker php artisan queue:work
```

Подробности по архитектуре и событиям — в каталоге `docs/`. TODO: заполнить спецификации.

