<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему PDO</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .login-header p {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-1px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c66;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .test-accounts {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }
        
        .test-accounts h3 {
            color: #666;
            font-size: 14px;
            margin: 0 0 10px 0;
            text-align: center;
        }
        
        .account-list {
            font-size: 12px;
            color: #888;
            line-height: 1.5;
        }
        
        .account-item {
            margin-bottom: 5px;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .account-item:hover {
            background-color: #f5f5f5;
        }
        
        .account-email {
            font-weight: 600;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🎓 PDO</h1>
            <p>Система управления образовательным процессом</p>
        </div>

        @if ($errors->any())
            <div class="error-message">
                @foreach ($errors->all() as $error)
                    {{ $error }}<br>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       required 
                       autocomplete="email"
                       placeholder="Введите ваш email">
            </div>
            
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="current-password"
                       placeholder="Введите пароль">
            </div>
            
            <button type="submit" class="btn-login">Войти</button>
        </form>

        <div class="test-accounts">
            <h3>🧪 Тестовые аккаунты</h3>
            <div class="account-list">
                <div class="account-item" onclick="fillLogin('admin@demo.local', 'password')">
                    <span class="account-email">admin@demo.local</span> / password <small>(Владелец тенанта)</small>
                </div>
                <div class="account-item" onclick="fillLogin('admin@example.com', 'admin123')">
                    <span class="account-email">admin@example.com</span> / admin123 <small>(Системный админ)</small>
                </div>
                <div class="account-item" onclick="fillLogin('teacher@example.com', 'teacher123')">
                    <span class="account-email">teacher@example.com</span> / teacher123 <small>(Преподаватель)</small>
                </div>
                <div class="account-item" onclick="fillLogin('student@example.com', 'student123')">
                    <span class="account-email">student@example.com</span> / student123 <small>(Студент)</small>
                </div>
                <div class="account-item" onclick="fillLogin('parent@example.com', 'parent123')">
                    <span class="account-email">parent@example.com</span> / parent123 <small>(Родитель)</small>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fillLogin(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>