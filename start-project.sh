#!/bin/bash

echo "🚀 Запуск PDO Educational System..."

# Проверка Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker не найден. Установите Docker и Docker Compose."
    exit 1
fi

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose не найден. Установите Docker Compose."
    exit 1
fi

echo "✅ Docker и Docker Compose найдены"

# Остановка существующих контейнеров
echo "🛑 Остановка существующих контейнеров..."
docker compose down

# Создание и запуск контейнеров
echo "🏗️ Создание и запуск контейнеров..."
docker compose up -d --build

# Ожидание запуска сервисов
echo "⏳ Ожидание запуска сервисов..."
sleep 30

# Проверка статуса контейнеров
echo "🔍 Проверка статуса контейнеров..."
docker compose ps

# Генерация ключей приложения
echo "🔑 Генерация ключей приложения..."
docker compose exec php-fpm php artisan key:generate --force
docker compose exec php-fpm php artisan jwt:secret --force

# Выполнение миграций
echo "🗄️ Выполнение миграций..."
docker compose exec php-fpm php artisan migrate --force

# Заполнение базы тестовыми данными
echo "📊 Заполнение базы тестовыми данными..."
docker compose exec php-fpm php artisan db:seed --force

# Создание символических ссылок
echo "🔗 Создание символических ссылок..."
docker compose exec php-fmp php artisan storage:link

# Очистка кеша
echo "🧹 Очистка кеша..."
docker compose exec php-fpm php artisan config:clear
docker compose exec php-fpm php artisan cache:clear
docker compose exec php-fpm php artisan route:clear
docker compose exec php-fpm php artisan view:clear

echo ""
echo "🎉 Проект успешно запущен!"
echo ""
echo "📋 Доступные сервисы:"
echo "   🌐 Основное приложение: http://localhost:8000"
echo "   🖥️ Frontend (Vite): http://localhost:5173"
echo "   💾 MinIO Console: http://localhost:9001"
echo "   🗄️ PostgreSQL: localhost:5432"
echo "   📊 Prometheus: http://localhost:9090 (профиль dev)"
echo "   📈 Grafana: http://localhost:3001 (профиль dev)"
echo ""
echo "🔐 Основные учетные записи (пароль: Password123!):"
echo "   👨‍💼 Администратор: admin@college.edu"
echo "   👩‍🏫 Преподаватель: math.teacher@college.edu"
echo "   👨‍🎓 Студент: student1@college.edu"
echo "   👨‍👩‍👧‍👦 Родитель: parent1@college.edu"
echo ""
echo "📚 Полный список учетных записей: docs/TEST_DATA.md"
echo ""
echo "🛠️ Полезные команды:"
echo "   docker compose logs -f                    # Просмотр логов"
echo "   docker compose exec php-fpm php artisan  # Выполнение artisan команд"
echo "   docker compose down                       # Остановка проекта"
echo ""
echo "🚀 Готово к работе!"