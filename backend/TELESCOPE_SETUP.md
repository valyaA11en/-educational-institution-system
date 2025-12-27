# Laravel Telescope Setup

Telescope настроен для работы только в dev окружении.

## Установка

После добавления `laravel/telescope` в `composer.json`, выполните:

```bash
composer install
php artisan telescope:install
php artisan migrate
```

## Настройка

Telescope автоматически регистрируется только в окружениях:
- `local`
- `dev`
- `development`

Для других окружений Telescope отключён.

## Доступ

Telescope доступен по адресу: `/telescope`

По умолчанию доступ разрешён только в dev окружении. Для настройки авторизации см. `App\Providers\TelescopeServiceProvider` (создаётся при установке).

## Отключение

Чтобы отключить Telescope, установите в `.env`:

```
TELESCOPE_ENABLED=false
```

Или удалите TelescopeServiceProvider из `bootstrap/providers.php`.


