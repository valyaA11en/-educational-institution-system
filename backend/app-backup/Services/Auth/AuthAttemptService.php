<?php

namespace App\Services\Auth;

use App\Models\AuthAttempt;
use Illuminate\Support\Facades\Cache;

class AuthAttemptService
{
    protected int $maxAttempts = 10;
    protected int $lockoutMinutes = 15;

    public function recordFailedAttempt(string $identifier, string $type = 'email'): void
    {
        // Use cache for faster lookups
        $key = "auth_attempts:{$type}:{$identifier}";
        
        $attempts = Cache::get($key, 0);
        $attempts++;

        // Store in cache for 15 minutes
        Cache::put($key, $attempts, now()->addMinutes($this->lockoutMinutes));

        // Also store in database for audit
        $authAttempt = AuthAttempt::firstOrNew([
            'identifier' => $identifier,
            'type' => $type,
        ]);

        $authAttempt->incrementAttempts();

        // Log to audit log
        \App\Models\AuditLog::create([
            'user_id' => null,
            'action' => 'auth.failed',
            'entity' => 'auth',
            'entity_id' => null,
            'after_json' => [
                'identifier' => $identifier,
                'type' => $type,
                'attempts' => $attempts,
            ],
            'ip' => request()->ip(),
        ]);
    }

    public function recordSuccess(string $identifier, string $type = 'email'): void
    {
        $key = "auth_attempts:{$type}:{$identifier}";
        Cache::forget($key);

        $authAttempt = AuthAttempt::where('identifier', $identifier)
            ->where('type', $type)
            ->first();

        if ($authAttempt) {
            $authAttempt->resetAttempts();
        }
    }

    public function isLocked(string $identifier, string $type = 'email'): bool
    {
        $key = "auth_attempts:{$type}:{$identifier}";
        $attempts = Cache::get($key, 0);

        if ($attempts >= $this->maxAttempts) {
            // Check database for lockout time
            $authAttempt = AuthAttempt::where('identifier', $identifier)
                ->where('type', $type)
                ->first();

            if ($authAttempt && $authAttempt->isLocked()) {
                return true;
            }
        }

        return false;
    }

    public function getRemainingLockoutTime(string $identifier, string $type = 'email'): ?int
    {
        $authAttempt = AuthAttempt::where('identifier', $identifier)
            ->where('type', $type)
            ->first();

        if ($authAttempt && $authAttempt->isLocked()) {
            return now()->diffInSeconds($authAttempt->locked_until);
        }

        return null;
    }
}

