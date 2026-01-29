<?php

namespace Tests;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper to create a user with a role and tenant, and return a JWT token
     * 
     * @param string|null $roleName Role name (e.g., 'студент', 'родитель', 'преподаватель')
     * @param Tenant|null $tenant Tenant instance (if null, creates a new one)
     * @param User|null $user User instance (if null, creates a new one)
     * @return string JWT token
     */
    protected function loginAs(?string $roleName = null, ?Tenant $tenant = null, ?User $user = null): string
    {
        if (!$tenant) {
            $tenant = Tenant::factory()->create();
        }

        if (!$user) {
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'password_hash' => Hash::make('password'),
            ]);
        }

        // Set tenant context
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        // Assign role if provided
        if ($roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            if (!$user->roles()->where('name', $roleName)->exists()) {
                $user->roles()->attach($role->id);
            }
        }

        return JWTAuth::fromUser($user);
    }
}
