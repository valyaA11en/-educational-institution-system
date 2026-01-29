<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_refresh_flow(): void
    {
        $password = 'secret-password';

        /** @var User $user */
        $user = User::query()->create([
            'fio' => 'Test User',
            'email' => 'user@example.com',
            'phone' => null,
            'password_hash' => Hash::make($password),
            'status' => 'active',
        ]);

        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $loginResponse
            ->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
                'user' => [
                    'id',
                    'email',
                ],
            ]);

        $refreshToken = $loginResponse->json('refresh_token');

        // Refresh
        $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshResponse
            ->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ]);
    }
}










