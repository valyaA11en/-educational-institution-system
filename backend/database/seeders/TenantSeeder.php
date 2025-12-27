<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\TenantMember;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Tenant',
                'timezone' => 'Europe/Amsterdam',
            ]
        );

        // Create demo admin user if not exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'fio' => 'Demo Admin',
                'password_hash' => \Hash::make('password'),
                'status' => 'active',
            ]
        );

        // Add admin as owner of demo tenant
        TenantMember::firstOrCreate(
            ['tenant_id' => $demo->id, 'user_id' => $admin->id],
            ['role_in_tenant' => 'owner']
        );
    }
}

