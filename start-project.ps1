# PowerShell скрипт для запуска PDO Educational System

Write-Host "🚀 Запуск PDO Educational System..." -ForegroundColor Green

# Проверка Docker
try {
    $dockerVersion = docker --version
    Write-Host "✅ $dockerVersion" -ForegroundColor Green
}
catch {
    Write-Host "❌ Docker не найден. Установите Docker Desktop." -ForegroundColor Red
    exit 1
}

try {
    $composeVersion = docker compose version
    Write-Host "✅ $composeVersion" -ForegroundColor Green
}
catch {
    Write-Host "❌ Docker Compose не найден. Обновите Docker Desktop." -ForegroundColor Red
    exit 1
}

# Остановка существующих контейнеров
Write-Host "🛑 Остановка существующих контейнеров..." -ForegroundColor Yellow
docker compose down

# Создание и запуск контейнеров
Write-Host "🏗️ Создание и запуск контейнеров..." -ForegroundColor Yellow
docker compose up -d --build

# Ожидание запуска сервисов
Write-Host "⏳ Ожидание запуска сервисов..." -ForegroundColor Yellow
Start-Sleep -Seconds 30

# Проверка статуса контейнеров
Write-Host "🔍 Проверка статуса контейнеров..." -ForegroundColor Yellow
docker compose ps

# Генерация ключей приложения
Write-Host "🔑 Генерация ключей приложения..." -ForegroundColor Yellow
docker compose exec php-fpm php artisan key:generate --force
docker compose exec php-fpm php artisan jwt:secret --force

# Выполнение миграций
Write-Host "🗄️ Выполнение миграций..." -ForegroundColor Yellow
docker compose exec php-fpm php artisan migrate --force

# Заполнение базы тестовыми данными
Write-Host "📊 Заполнение базы тестовыми данными..." -ForegroundColor Yellow
docker compose exec php-fpm php artisan db:seed --force

# Создание символических ссылок
Write-Host "🔗 Создание символических ссылок..." -ForegroundColor Yellow
docker compose exec php-fpm php artisan storage:link

# Очистка кеша
Write-Host "🧹 Очистка кеша..." -ForegroundColor Yellow
docker compose exec php-fpm php artisan config:clear
docker compose exec php-fpm php artisan cache:clear
docker compose exec php-fpm php artisan route:clear
docker compose exec php-fmp php artisan view:clear

Write-Host ""
Write-Host "🎉 Проект успешно запущен!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Доступные сервисы:" -ForegroundColor Cyan
Write-Host "   🌐 Основное приложение: http://localhost:8000" -ForegroundColor White
Write-Host "   🖥️ Frontend (Vite): http://localhost:5173" -ForegroundColor White
Write-Host "   💾 MinIO Console: http://localhost:9001" -ForegroundColor White
Write-Host "   🗄️ PostgreSQL: localhost:5432" -ForegroundColor White
Write-Host "   📊 Prometheus: http://localhost:9090 (профиль dev)" -ForegroundColor White
Write-Host "   📈 Grafana: http://localhost:3001 (профиль dev)" -ForegroundColor White
Write-Host ""
Write-Host "🔐 Основные учетные записи (пароль: Password123!):" -ForegroundColor Cyan
Write-Host "   👨‍💼 Администратор: admin@college.edu" -ForegroundColor White
Write-Host "   👩‍🏫 Преподаватель: math.teacher@college.edu" -ForegroundColor White
Write-Host "   👨‍🎓 Студент: student1@college.edu" -ForegroundColor White
Write-Host "   👨‍👩‍👧‍👦 Родитель: parent1@college.edu" -ForegroundColor White
Write-Host ""
Write-Host "📚 Полный список учетных записей: docs\TEST_DATA.md" -ForegroundColor Cyan
Write-Host ""
Write-Host "🛠️ Полезные команды:" -ForegroundColor Cyan
Write-Host "   docker compose logs -f                    # Просмотр логов" -ForegroundColor White
Write-Host "   docker compose exec php-fpm php artisan  # Выполнение artisan команд" -ForegroundColor White
Write-Host "   docker compose down                       # Остановка проекта" -ForegroundColor White
Write-Host ""
Write-Host "🚀 Готово к работе!" -ForegroundColor Green

# Попытка открыть браузер
try {
    Start-Process "http://localhost:8000"
    Write-Host "🌐 Браузер открыт автоматически" -ForegroundColor Green
}
catch {
    Write-Host "💡 Откройте браузер и перейдите по адресу: http://localhost:8000" -ForegroundColor Yellow
}