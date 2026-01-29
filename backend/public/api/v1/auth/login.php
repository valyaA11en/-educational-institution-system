<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple mock login response
echo json_encode([
    'access_token' => 'mock_token_12345',
    'token_type' => 'Bearer',
    'expires_in' => 3600,
    'user' => [
        'id' => 1,
        'email' => 'admin@test.local',
        'name' => 'Admin User',
        'roles' => ['admin']
    ]
]);