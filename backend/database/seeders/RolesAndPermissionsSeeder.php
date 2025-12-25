<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissionsCatalog = PermissionsCatalog::all();

            $permissionModels = [];
            foreach ($permissionsCatalog as $code => $description) {
                $permissionModels[$code] = Permission::firstOrCreate(
                    ['code' => $code],
                    ['description' => $description],
                );
            }

            $roles = [
                'admin',
                'management',
                'methodist',
                'teacher',
                'curator',
                'scheduler',
                'student',
                'parent',
            ];

            $roleModels = [];
            foreach ($roles as $roleName) {
                $roleModels[$roleName] = Role::firstOrCreate(['name' => $roleName]);
            }

            // Admin: все права
            $roleModels['admin']->permissions()->sync(array_column($permissionModels, 'id'));

            // Руководство
            $roleModels['management']->permissions()->sync([
                $permissionModels['schedule.view']->id,
                $permissionModels['journal.reports.view']->id,
                $permissionModels['analytics.view']->id,
                $permissionModels['documents.view']->id,
                $permissionModels['audit.view']->id,
            ]);

            // Методист
            $roleModels['methodist']->permissions()->sync([
                $permissionModels['directory.manage']->id,
                $permissionModels['schedule.view']->id,
                $permissionModels['schedule.edit']->id,
                $permissionModels['schedule.publish']->id,
                $permissionModels['schedule.force_override']->id,
                $permissionModels['schedule.replacements.manage']->id,
                $permissionModels['schedule.duties.manage']->id,
                $permissionModels['journal.reports.view']->id,
                $permissionModels['documents.registry']->id,
            ]);

            // Преподаватель
            $roleModels['teacher']->permissions()->sync([
                $permissionModels['schedule.view']->id,
                $permissionModels['journal.view']->id,
                $permissionModels['journal.grade.create']->id,
                $permissionModels['journal.grade.edit']->id,
                $permissionModels['journal.attendance.edit']->id,
                $permissionModels['assignments.view']->id,
                $permissionModels['assignments.create']->id,
                $permissionModels['assignments.grade']->id,
                $permissionModels['materials.view']->id,
                $permissionModels['materials.create']->id,
                $permissionModels['chat.view']->id,
                $permissionModels['chat.write']->id,
                $permissionModels['notifications.view']->id,
            ]);

            // Куратор
            $roleModels['curator']->permissions()->sync([
                $permissionModels['schedule.view']->id,
                $permissionModels['journal.view']->id,
                $permissionModels['journal.reports.view']->id,
                $permissionModels['chat.view']->id,
                $permissionModels['chat.write']->id,
                $permissionModels['notifications.view']->id,
            ]);

            // Составитель расписания
            $roleModels['scheduler']->permissions()->sync([
                $permissionModels['schedule.view']->id,
                $permissionModels['schedule.edit']->id,
                $permissionModels['schedule.publish']->id,
                $permissionModels['schedule.force_override']->id,
                $permissionModels['schedule.replacements.manage']->id,
                $permissionModels['schedule.duties.manage']->id,
            ]);

            // Студент
            $roleModels['student']->permissions()->sync([
                $permissionModels['schedule.view']->id,
                $permissionModels['journal.view']->id,
                $permissionModels['assignments.view']->id,
                $permissionModels['assignments.submit']->id,
                $permissionModels['materials.view']->id,
                $permissionModels['chat.view']->id,
                $permissionModels['chat.write']->id,
                $permissionModels['notifications.view']->id,
            ]);

            // Родитель
            $roleModels['parent']->permissions()->sync([
                $permissionModels['journal.view']->id,
                $permissionModels['assignments.view']->id,
                $permissionModels['notifications.view']->id,
                $permissionModels['documents.view']->id,
                $permissionModels['chat.view']->id,
            ]);

            // Demo users
            $adminUser = User::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'fio' => 'Admin User',
                    'phone' => null,
                    'password_hash' => Hash::make('admin123'),
                    'status' => 'active',
                ],
            );

            $teacherUser = User::firstOrCreate(
                ['email' => 'teacher@example.com'],
                [
                    'fio' => 'Teacher User',
                    'phone' => null,
                    'password_hash' => Hash::make('teacher123'),
                    'status' => 'active',
                ],
            );

            $studentUser = User::firstOrCreate(
                ['email' => 'student@example.com'],
                [
                    'fio' => 'Student User',
                    'phone' => null,
                    'password_hash' => Hash::make('student123'),
                    'status' => 'active',
                ],
            );

            $parentUser = User::firstOrCreate(
                ['email' => 'parent@example.com'],
                [
                    'fio' => 'Parent User',
                    'phone' => null,
                    'password_hash' => Hash::make('parent123'),
                    'status' => 'active',
                ],
            );

            $adminUser->roles()->sync([$roleModels['admin']->id]);
            $teacherUser->roles()->sync([$roleModels['teacher']->id]);
            $studentUser->roles()->sync([$roleModels['student']->id]);
            $parentUser->roles()->sync([$roleModels['parent']->id]);
        });
    }
}


