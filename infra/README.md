# Infra (dev)

## Требования

- Docker / Docker Compose v2
- Заполненный файл `.env` в корне репозитория (на основе `env.example`)

## Сервисы

- `nginx` — прокси:
  - `/` → frontend (Vite dev server)
  - `/api` → backend (Laravel через php-fpm)
  - `/ws` → WebSocket сервис
- `php-fpm` — Laravel backend
- `queue-worker` — Laravel queue worker (TODO: Horizon)
- `postgres` — PostgreSQL с volume `postgres_data`
- `redis` — Redis с volume `redis_data`
- `minio` — S3-совместимое хранилище с volume `minio_data`
- `ws` — WebSocket (Laravel Echo Server)
- `frontend` — Vite dev server для Vue 3

## Переменные окружения

Все сервисы используют файл `../.env` через `env_file`.
Минимальный набор переменных:

```env
APP_ENV=local
APP_DEBUG=true
APP_PORT=8000

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PORT=6379

MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=minioadmin
MINIO_PORT=9000
MINIO_CONSOLE_PORT=9001

VITE_API_URL=http://localhost:8000/api
VITE_WEBSOCKET_URL=ws://localhost:8000/ws
```

## Запуск стенда

Из корня репозитория:

```bash
docker compose -f infra/docker-compose.yml up -d --build
```

Либо просто:

```bash
docker compose up -d --build
```

если используется корневой `docker-compose.yml`, расширяющий `infra/docker-compose.yml`.

## Доступ

- Frontend: `http://localhost:8000/`
- Backend API: `http://localhost:8000/api`
- WebSocket: `ws://localhost:8000/ws`
- PostgreSQL: `localhost:5432`
- Redis: `localhost:6379`
- MinIO:
  - API: `http://localhost:9000`
  - Console: `http://localhost:9001`

## Базовые команды

```bash
# Логи
docker compose logs -f

# Рестарт сервиса
docker compose restart nginx

# Миграции
docker compose exec php-fpm php artisan migrate

# Очередь
docker compose exec queue-worker php artisan queue:work

# Остановка
docker compose down
```









