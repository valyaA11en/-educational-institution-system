# Backend Setup

## Установка зависимостей

```bash
composer install
```

## Настройка окружения

1. Скопируйте `.env.example` в `.env`:
```bash
cp .env.example .env
```

2. Сгенерируйте ключ приложения:
```bash
php artisan key:generate
```

3. Сгенерируйте JWT секрет:
```bash
php artisan jwt:secret
```

## Настройка базы данных

1. Убедитесь, что PostgreSQL запущен и доступен
2. Запустите миграции:
```bash
php artisan migrate
```

## Публикация конфигураций

1. Публикация конфигурации Horizon:
```bash
php artisan horizon:install
```

2. Публикация конфигурации WebSockets:
```bash
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="config"
```

3. Публикация миграций WebSockets:
```bash
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="migrations"
```

4. Запустите миграции WebSockets:
```bash
php artisan migrate
```

## Запуск сервисов

### Development сервер
```bash
php artisan serve
```

### Horizon (очереди)
```bash
php artisan horizon
```

### WebSockets сервер
```bash
php artisan websockets:serve
```

## Структура доменов

Домены организованы в `app/Domains/`:
- `Auth` - Аутентификация
- `RBAC` - Роли и права доступа
- `Directory` - Справочники
- `Schedule` - Расписание
- `Journal` - Журнал
- `Assignments` - Задания
- `Documents` - Документы
- `Notifications` - Уведомления
- `Chat` - Чат
- `Contests` - Конкурсы
- `Tickets` - Тикеты
- `Rules` - Правила
- `Audit` - Аудит
- `Realtime` - Реалтайм события

## API Endpoints

### Authentication
- `POST /api/v1/auth/login` - Вход
- `POST /api/v1/auth/refresh` - Обновление токена
- `GET /api/v1/auth/me` - Текущий пользователь (требует auth)
- `POST /api/v1/auth/logout` - Выход (требует auth)

## Middleware

- `auth:api` - JWT аутентификация
- `permission` - Проверка прав доступа
- `role` - Проверка роли
- `object.permission` - Проверка object-level прав


