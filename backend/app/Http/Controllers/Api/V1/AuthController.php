<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $authAttemptService = app(\App\Services\Auth\AuthAttemptService::class);
        $email = $credentials['email'];
        $ip = $request->ip();

        // Check if locked by email
        if ($authAttemptService->isLocked($email, 'email')) {
            $remaining = $authAttemptService->getRemainingLockoutTime($email, 'email');
            return response()->json([
                'message' => 'Too many failed login attempts. Please try again later.',
                'locked_until' => now()->addSeconds($remaining ?? 0)->toIso8601String(),
            ], 429);
        }

        // Check if locked by IP
        if ($authAttemptService->isLocked($ip, 'ip')) {
            $remaining = $authAttemptService->getRemainingLockoutTime($ip, 'ip');
            return response()->json([
                'message' => 'Too many failed login attempts from this IP. Please try again later.',
                'locked_until' => now()->addSeconds($remaining ?? 0)->toIso8601String(),
            ], 429);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            // Record failed attempt
            $authAttemptService->recordFailedAttempt($email, 'email');
            $authAttemptService->recordFailedAttempt($ip, 'ip');

            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Record successful login
        $authAttemptService->recordSuccess($email, 'email');
        $authAttemptService->recordSuccess($ip, 'ip');

        // Check if 2FA is required
        $requires2FA = \App\Models\TwoFactorAuth::where('user_id', $user->id)
            ->where('enabled', true)
            ->exists();

        // If 2FA is required, return temp token instead of access token
        if ($requires2FA) {
            $tempToken = $this->createTempToken($user);
            
            return response()->json([
                'requires_2fa' => true,
                'temp_token' => $tempToken->token,
                'expires_in' => 300, // 5 minutes
            ]);
        }

        // Set tenant context if provided or use user's default tenant
        $tenantId = $request->input('tenant_id');
        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant) {
                app()->instance('tenant', $tenant);
                app()->instance('tenant_id', $tenant->id);
            }
        } elseif ($user->tenant_id) {
            app()->instance('tenant_id', $user->tenant_id);
        }

        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $this->createRefreshToken($user);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'requires_2fa' => false,
            'user' => [
                'id' => $user->id,
                'fio' => $user->fio,
                'email' => $user->email,
                // TODO: вернуть роли и права пользователя
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $refreshToken = RefreshToken::query()
            ->where('token_hash', hash('sha256', $request->refresh_token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $refreshToken) {
            return response()->json([
                'message' => 'Invalid refresh token',
            ], 401);
        }

        $user = $refreshToken->user;

        // Удаляем старый refresh token
        $refreshToken->delete();

        // Restore tenant context from token if available
        $token = JWTAuth::getToken();
        if ($token) {
            $payload = JWTAuth::getPayload($token);
            if ($payload->has('tenant_id')) {
                $tenantId = $payload->get('tenant_id');
                app()->instance('tenant_id', $tenantId);
            }
        }

        // Создаем новые токены
        $accessToken = JWTAuth::fromUser($user);
        $newRefreshToken = $this->createRefreshToken($user);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken->token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    public function verify2FA(Request $request): JsonResponse
    {
        $request->validate([
            'temp_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        // Find and validate temp token
        $tempToken = \App\Models\TwoFactorTempToken::where('token', $request->temp_token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tempToken || !$tempToken->isValid()) {
            return response()->json(['message' => 'Invalid or expired temp token'], 401);
        }

        $user = $tempToken->user;

        // Verify 2FA code
        $twoFactorService = app(\App\Services\Auth\TwoFactorService::class);
        
        if (!$twoFactorService->verifyWithRecovery($user, $request->code)) {
            return response()->json(['message' => 'Invalid code'], 401);
        }

        // Delete temp token
        $tempToken->delete();

        // Set tenant context
        if ($user->tenant_id) {
            app()->instance('tenant_id', $user->tenant_id);
        }

        // Generate real tokens
        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $this->createRefreshToken($user);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id' => $user->id,
                'fio' => $user->fio,
                'email' => $user->email,
            ],
        ]);
    }

    protected function createTempToken(User $user): \App\Models\TwoFactorTempToken
    {
        $token = Str::random(64);

        return \App\Models\TwoFactorTempToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'fio' => $user->fio,
                'email' => $user->email,
                // TODO: вернуть роли и права пользователя
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = JWTAuth::getToken();

        if ($token) {
            JWTAuth::invalidate($token);
        }

        // Удаляем refresh token если передан
        if ($request->has('refresh_token')) {
            RefreshToken::query()
                ->where('token_hash', hash('sha256', $request->refresh_token))
                ->update(['revoked_at' => now()]);
        }

        return response()->json(['message' => 'Logged out']);
    }

    protected function createRefreshToken(User $user): RefreshToken
    {
        $plain = Str::random(64);

        return RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(config('jwt.refresh_ttl')),
        ])->setAttribute('token', $plain);
    }
}


