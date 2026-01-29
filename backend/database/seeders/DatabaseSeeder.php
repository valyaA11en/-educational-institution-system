<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            RolesAndPermissionsSeeder::class,
            // FullDemoSeeder::class, // Temporarily disabled - requires additional models
            // DefaultRulesSeeder::class, // May require additional models
            // CertificateTemplateSeeder::class, // May require additional models
            // GostDocumentTemplateSeeder::class, // May require additional models
        ]);
        
        $this->command->info('🎉 Core database seeded successfully!');
        $this->command->info('🔐 Available test accounts:');
        $this->command->info('   Demo Tenant Owner: admin@demo.local / password');
        $this->command->info('   System Admin: admin@example.com / admin123');
        $this->command->info('   Teacher: teacher@example.com / teacher123');
        $this->command->info('   Student: student@example.com / student123');
        $this->command->info('   Parent: parent@example.com / parent123');
        $this->command->info('💡 Note: FullDemoSeeder disabled - requires additional models');
    }
}
