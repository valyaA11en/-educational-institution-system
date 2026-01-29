<?php

namespace App\Support;

use RuntimeException;

class ValidateEnvironment
{
    /**
     * Validate required environment variables
     *
     * @throws RuntimeException
     */
    public static function validate(): void
    {
        $errors = [];

        // Helper to get env value (tries env(), then getenv(), then $_ENV)
        $getEnv = function (string $key): ?string {
            $value = env($key);
            if ($value !== null) {
                return $value;
            }
            $value = getenv($key);
            if ($value !== false) {
                return $value;
            }
            return $_ENV[$key] ?? null;
        };

        // APP_KEY
        if (empty($getEnv('APP_KEY'))) {
            $errors[] = 'APP_KEY is required. Run: php artisan key:generate';
        }

        // Database
        $dbRequired = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
        foreach ($dbRequired as $key) {
            if (empty($getEnv($key))) {
                $errors[] = "Database configuration missing: {$key}";
            }
        }

        // Redis
        if (empty($getEnv('REDIS_HOST'))) {
            $errors[] = 'REDIS_HOST is required';
        }
        if (empty($getEnv('REDIS_PORT'))) {
            $errors[] = 'REDIS_PORT is required';
        }

        // JWT
        if (empty($getEnv('JWT_SECRET'))) {
            $errors[] = 'JWT_SECRET is required. Generate a secure random string (32+ characters)';
        }

        // S3 / MinIO credentials
        if (empty($getEnv('AWS_ACCESS_KEY_ID'))) {
            $errors[] = 'AWS_ACCESS_KEY_ID is required for S3/MinIO';
        }
        if (empty($getEnv('AWS_SECRET_ACCESS_KEY'))) {
            $errors[] = 'AWS_SECRET_ACCESS_KEY is required for S3/MinIO';
        }
        if (empty($getEnv('AWS_BUCKET'))) {
            $errors[] = 'AWS_BUCKET is required for S3/MinIO';
        }

        if (!empty($errors)) {
            $message = "Environment validation failed:\n\n" . implode("\n", $errors);
            $message .= "\n\nPlease check your .env file and ensure all required variables are set.";
            
            throw new RuntimeException($message);
        }
    }
}

