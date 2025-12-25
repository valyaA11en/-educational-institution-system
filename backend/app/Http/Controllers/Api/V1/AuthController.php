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

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $this->createRefreshToken($user);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
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
                'name' => $user->name,
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


