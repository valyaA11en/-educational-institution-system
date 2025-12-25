<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class RbacPermissionDeniedTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_denied_without_permission(): void
    {
        $user = User::query()->create([
            'fio' => 'RBAC User',
            'email' => 'rbac@example.com',
            'phone' => null,
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rules');

        $response->assertStatus(403);
    }
}


