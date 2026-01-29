<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test - PDO</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .test-section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .result { background: #e8f5e8; padding: 10px; border-radius: 4px; margin: 10px 0; white-space: pre-wrap; font-family: monospace; }
        .error { background: #ffe8e8; color: #c33; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        input, select { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🧪 API Testing Tool - PDO</h1>
    
    <div class="test-section">
        <h2>🔐 Test Authentication</h2>
        <div>
            <input type="email" id="email" placeholder="Email" value="admin@example.com">
            <input type="password" id="password" placeholder="Password" value="admin123">
            <button onclick="testLogin()">Login</button>
            <button onclick="testMe()">Get User Info</button>
            <button onclick="testLogout()">Logout</button>
        </div>
        <div id="auth-result" class="result"></div>
    </div>

    <div class="test-section">
        <h2>📡 WebSocket Status</h2>
        <button onclick="testWebSocket()">Test WebSocket</button>
        <div id="ws-result" class="result"></div>
    </div>

    <div class="test-section">
        <h2>🎯 Available Test Accounts</h2>
        <div style="font-family: monospace; font-size: 14px;">
            <div><strong>admin@example.com</strong> / admin123 (System Admin)</div>
            <div><strong>admin@demo.local</strong> / password (Tenant Owner)</div>
            <div><strong>teacher@example.com</strong> / teacher123 (Teacher)</div>
            <div><strong>student@example.com</strong> / student123 (Student)</div>
            <div><strong>parent@example.com</strong> / parent123 (Parent)</div>
        </div>
    </div>

    <script>
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        async function apiCall(method, url, data = null) {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include', // Include cookies for session auth
            };
            
            if (data) {
                options.body = JSON.stringify(data);
            }
            
            try {
                console.log(`Making ${method} request to ${url}`, data);
                const response = await fetch(url, options);
                const result = await response.json();
                console.log('Response:', result);
                return { status: response.status, data: result };
            } catch (error) {
                console.error('API Error:', error);
                return { status: 0, error: error.message };
            }
        }
        
        async function testLogin() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            const result = await apiCall('POST', '/api/v1/auth/login', { email, password });
            
            document.getElementById('auth-result').textContent = 
                `Status: ${result.status}\n` +
                `Response: ${JSON.stringify(result.data || result.error, null, 2)}`;
                
            if (result.data && result.data.success) {
                document.getElementById('auth-result').classList.remove('error');
            } else {
                document.getElementById('auth-result').classList.add('error');
            }
        }
        
        async function testMe() {
            const result = await apiCall('GET', '/api/v1/auth/me');
            
            document.getElementById('auth-result').textContent = 
                `Status: ${result.status}\n` +
                `Response: ${JSON.stringify(result.data || result.error, null, 2)}`;
                
            if (result.data && result.data.success) {
                document.getElementById('auth-result').classList.remove('error');
            } else {
                document.getElementById('auth-result').classList.add('error');
            }
        }
        
        async function testLogout() {
            const result = await apiCall('POST', '/api/v1/auth/logout');
            
            document.getElementById('auth-result').textContent = 
                `Status: ${result.status}\n` +
                `Response: ${JSON.stringify(result.data || result.error, null, 2)}`;
        }
        
        function testWebSocket() {
            const wsResult = document.getElementById('ws-result');
            wsResult.textContent = 'Connecting to WebSocket...';
            
            try {
                const ws = new WebSocket('ws://localhost:8000/ws');
                
                ws.onopen = function() {
                    wsResult.textContent = '✅ WebSocket connected successfully!';
                    wsResult.classList.remove('error');
                    setTimeout(() => ws.close(), 2000);
                };
                
                ws.onerror = function(error) {
                    wsResult.textContent = '❌ WebSocket connection failed: ' + JSON.stringify(error);
                    wsResult.classList.add('error');
                };
                
                ws.onclose = function(event) {
                    if (event.wasClean) {
                        wsResult.textContent += '\n✅ WebSocket closed cleanly';
                    } else {
                        wsResult.textContent += '\n❌ WebSocket connection died';
                        wsResult.classList.add('error');
                    }
                };
            } catch (error) {
                wsResult.textContent = '❌ WebSocket error: ' + error.message;
                wsResult.classList.add('error');
            }
        }
    </script>
</body>
</html>