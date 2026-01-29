<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления - PDO</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            text-decoration: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .welcome-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-card h2 {
            color: #667eea;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        
        .welcome-card p {
            color: #666;
            font-size: 16px;
            margin: 0;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .info-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-card p, .info-card li {
            color: #666;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        
        .info-card ul {
            padding-left: 20px;
            margin: 10px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .api-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        
        .api-section h3 {
            margin: 0 0 20px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .api-endpoint {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        
        .api-endpoint strong {
            color: #667eea;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 14px;
        }
        
        .api-endpoint p {
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        
        .footer {
            text-align: center;
            padding: 40px 20px;
            color: #888;
            border-top: 1px solid #e5e5e5;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🎓 PDO - Панель управления</h1>
            <div class="user-info">
                <span class="user-name">{{ $user->fio }}</span>
                <span class="status-badge">{{ $user->status }}</span>
                <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" class="logout-btn">Выйти</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h2>🎉 Добро пожаловать в систему!</h2>
            <p>Вы успешно авторизовались в системе PDO. Система готова к работе.</p>
        </div>

        <div class="cards-grid">
            <div class="info-card">
                <h3>👤 Информация о пользователе</h3>
                <p><strong>ФИО:</strong> {{ $user->fio }}</p>
                <p><strong>Email:</strong> {{ $user->email }}</p>
                <p><strong>Статус:</strong> <span class="status-badge">{{ $user->status }}</span></p>
                <p><strong>ID:</strong> {{ $user->id }}</p>
                @if($user->tenant_id)
                    <p><strong>Тенант ID:</strong> {{ $user->tenant_id }}</p>
                @endif
            </div>

            <div class="info-card">
                <h3>🎯 Возможности системы</h3>
                <ul>
                    <li>Управление пользователями</li>
                    <li>Система ролей и разрешений</li>
                    <li>Мультитенантная архитектура</li>
                    <li>REST API</li>
                    <li>Аудит действий</li>
                    <li>Расписание занятий</li>
                    <li>Система оценок</li>
                    <li>Документооборот</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>🚀 Статус системы</h3>
                <p><strong>Laravel:</strong> 11.48.0</p>
                <p><strong>База данных:</strong> PostgreSQL</p>
                <p><strong>Кеш:</strong> Redis</p>
                <p><strong>Очереди:</strong> Redis</p>
                <p><strong>Сессии:</strong> Redis</p>
                <p><strong>Файлы:</strong> MinIO S3</p>
                <p><strong>Миграции:</strong> ✅ Выполнены</p>
                <p><strong>Сидеры:</strong> ✅ Загружены</p>
            </div>
        </div>

        <div class="api-section">
            <h3>🔌 API Endpoints</h3>
            
            <div class="api-endpoint">
                <strong>POST /api/v1/auth/login</strong>
                <p>Авторизация через API (возвращает токен)</p>
            </div>
            
            <div class="api-endpoint">
                <strong>GET /api/v1/auth/user</strong>
                <p>Получение информации о текущем пользователе</p>
            </div>
            
            <div class="api-endpoint">
                <strong>POST /api/v1/auth/logout</strong>
                <p>Выход из системы (отзыв токена)</p>
            </div>
            
            <div class="api-endpoint">
                <strong>GET /api/v1/users</strong>
                <p>Список пользователей (требует авторизации)</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>PDO - Система управления образовательным процессом</p>
        <p>Laravel 11.48.0 | PostgreSQL | Redis | Docker</p>
    </div>
</body>
</html>