<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentTarget;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Group;
use App\Models\Material;
use App\Models\MaterialTarget;
use App\Models\Room;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Models\Subgroup;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserLinkParentChild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // 1. Academic Year + Term
            $currentYear = date('Y');
            $termId = DB::table('terms')->insertGetId([
                'academic_year_id' => DB::table('academic_years')->insertGetId([
                    'name' => $currentYear . '-' . ($currentYear + 1),
                    'start_date' => $currentYear . '-09-01',
                    'end_date' => ($currentYear + 1) . '-08-31',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                'name' => 'Осенний семестр',
                'start_date' => $currentYear . '-09-01',
                'end_date' => $currentYear . '-12-31',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Groups + Subgroups
            $group1 = Group::firstOrCreate(
                ['code' => '2IS-31'],
                ['name' => '2ИС-31'],
            );
            $group2 = Group::firstOrCreate(
                ['code' => '1PR-12'],
                ['name' => '1ПР-12'],
            );

            $subgroup1A = Subgroup::firstOrCreate(
                ['code' => '2IS-31-A'],
                [
                    'group_id' => $group1->id,
                    'name' => '2ИС-31-A',
                ],
            );
            $subgroup1B = Subgroup::firstOrCreate(
                ['code' => '2IS-31-B'],
                [
                    'group_id' => $group1->id,
                    'name' => '2ИС-31-B',
                ],
            );
            $subgroup2A = Subgroup::firstOrCreate(
                ['code' => '1PR-12-A'],
                [
                    'group_id' => $group2->id,
                    'name' => '1ПР-12-A',
                ],
            );
            $subgroup2B = Subgroup::firstOrCreate(
                ['code' => '1PR-12-B'],
                [
                    'group_id' => $group2->id,
                    'name' => '1ПР-12-B',
                ],
            );

            // 3. Subjects
            $math = Subject::firstOrCreate(
                ['code' => 'MATH'],
                ['name' => 'Математика'],
            );
            $russian = Subject::firstOrCreate(
                ['code' => 'RUS'],
                ['name' => 'Русский'],
            );
            $informatics = Subject::firstOrCreate(
                ['code' => 'INF'],
                ['name' => 'Информатика'],
            );

            // 4. Time Slots
            $slots = [];
            $slotTimes = [
                ['start' => '08:00', 'end' => '09:30'],
                ['start' => '09:45', 'end' => '11:15'],
                ['start' => '11:30', 'end' => '13:00'],
                ['start' => '13:30', 'end' => '15:00'],
                ['start' => '15:15', 'end' => '16:45'],
                ['start' => '17:00', 'end' => '18:30'],
            ];

            for ($i = 1; $i <= 6; $i++) {
                $slots[$i] = TimeSlot::firstOrCreate(
                    ['name' => (string) $i],
                    [
                        'start_time' => $slotTimes[$i - 1]['start'],
                        'end_time' => $slotTimes[$i - 1]['end'],
                        'order' => $i,
                    ],
                );
            }

            // 5. Rooms
            $room101 = Room::firstOrCreate(
                ['code' => '101'],
                [
                    'name' => '101',
                    'capacity' => 30,
                ],
            );
            $room202 = Room::firstOrCreate(
                ['code' => '202'],
                [
                    'name' => '202',
                    'capacity' => 25,
                    'attributes' => ['pc' => true],
                ],
            );

            $room303 = Room::firstOrCreate(
                ['code' => '303'],
                [
                    'name' => '303',
                    'capacity' => 20,
                    'attributes' => ['lab' => true],
                ],
            );

            // 6. Users
            $password = Hash::make('Password123!');

            $admin = User::firstOrCreate(
                ['email' => 'admin@test.local'],
                [
                    'fio' => 'Администратор',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $admin->roles()->sync([DB::table('roles')->where('name', 'admin')->value('id')]);

            $director = User::firstOrCreate(
                ['email' => 'director@test.local'],
                [
                    'fio' => 'Директор',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $director->roles()->sync([DB::table('roles')->where('name', 'management')->value('id')]);

            $methodist = User::firstOrCreate(
                ['email' => 'methodist@test.local'],
                [
                    'fio' => 'Методист',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $methodist->roles()->sync([DB::table('roles')->where('name', 'methodist')->value('id')]);

            $curator = User::firstOrCreate(
                ['email' => 'curator@test.local'],
                [
                    'fio' => 'Куратор',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $curator->roles()->sync([DB::table('roles')->where('name', 'curator')->value('id')]);

            $scheduler = User::firstOrCreate(
                ['email' => 'scheduler@test.local'],
                [
                    'fio' => 'Составитель расписания',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $scheduler->roles()->sync([DB::table('roles')->where('name', 'scheduler')->value('id')]);

            $teacher1 = User::firstOrCreate(
                ['email' => 'teacher1@test.local'],
                [
                    'fio' => 'Преподаватель 1',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $teacher1->roles()->sync([DB::table('roles')->where('name', 'teacher')->value('id')]);

            $teacher2 = User::firstOrCreate(
                ['email' => 'teacher2@test.local'],
                [
                    'fio' => 'Преподаватель 2',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $teacher2->roles()->sync([DB::table('roles')->where('name', 'teacher')->value('id')]);

            $student1 = User::firstOrCreate(
                ['email' => 'student1@test.local'],
                [
                    'fio' => 'Студент 1',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $student1->roles()->sync([DB::table('roles')->where('name', 'student')->value('id')]);

            $student2 = User::firstOrCreate(
                ['email' => 'student2@test.local'],
                [
                    'fio' => 'Студент 2',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $student2->roles()->sync([DB::table('roles')->where('name', 'student')->value('id')]);

            $parent1 = User::firstOrCreate(
                ['email' => 'parent1@test.local'],
                [
                    'fio' => 'Родитель 1',
                    'password_hash' => $password,
                    'status' => 'active',
                ],
            );
            $parent1->roles()->sync([DB::table('roles')->where('name', 'parent')->value('id')]);

            // Parent-Child link
            UserLinkParentChild::firstOrCreate(
                [
                    'parent_user_id' => $parent1->id,
                    'student_user_id' => $student1->id,
                ],
                [
                    'status' => 'approved',
                    'link_code_hash' => Hash::make('link-' . $parent1->id . '-' . $student1->id),
                ],
            );

            // 7. Group memberships
            DB::table('group_members')->insertOrIgnore([
                [
                    'group_id' => $group1->id,
                    'user_id' => $student1->id,
                    'role_in_group' => 'student',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'group_id' => $group1->id,
                    'user_id' => $student2->id,
                    'role_in_group' => 'student',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'group_id' => $group1->id,
                    'user_id' => $curator->id,
                    'role_in_group' => 'curator',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // 8. Teacher-Subject-Group (via DB directly, no model yet)
            DB::table('teacher_subject_group')->insertOrIgnore([
                [
                    'teacher_user_id' => $teacher1->id,
                    'subject_id' => $math->id,
                    'group_id' => $group1->id,
                    'subgroup_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'teacher_user_id' => $teacher2->id,
                    'subject_id' => $informatics->id,
                    'group_id' => $group1->id,
                    'subgroup_id' => $subgroup1A->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // 9. Schedule Versions + Items
            $draftVersion = ScheduleVersion::create([
                'term_id' => $termId,
                'status' => 'draft',
                'created_by' => $scheduler->id,
            ]);

            $publishedVersion = ScheduleVersion::create([
                'term_id' => $termId,
                'status' => 'published',
                'created_by' => $scheduler->id,
                'published_at' => now()->subDays(2),
            ]);

            // Current week schedule items
            $today = now()->startOfWeek();
            $monday = $today->copy();
            $wednesday = $today->copy()->addDays(2);

            ScheduleItem::create([
                'version_id' => $publishedVersion->id,
                'date' => $monday,
                'time_slot_id' => $slots[1]->id,
                'group_id' => $group1->id,
                'subgroup_id' => null,
                'subject_id' => $math->id,
                'teacher_user_id' => $teacher1->id,
                'room_id' => $room101->id,
                'created_by' => $scheduler->id,
            ]);

            ScheduleItem::create([
                'version_id' => $publishedVersion->id,
                'date' => $monday,
                'time_slot_id' => $slots[2]->id,
                'group_id' => $group1->id,
                'subgroup_id' => $subgroup1A->id,
                'subject_id' => $informatics->id,
                'teacher_user_id' => $teacher2->id,
                'room_id' => $room202->id,
                'created_by' => $scheduler->id,
            ]);

            ScheduleItem::create([
                'version_id' => $publishedVersion->id,
                'date' => $wednesday,
                'time_slot_id' => $slots[3]->id,
                'group_id' => $group1->id,
                'subgroup_id' => null,
                'subject_id' => $russian->id,
                'teacher_user_id' => $teacher1->id,
                'room_id' => $room101->id,
                'created_by' => $scheduler->id,
            ]);

            // 10. Demo Assignment
            $assignment = Assignment::create([
                'subject_id' => $math->id,
                'teacher_user_id' => $teacher1->id,
                'title' => 'Контрольная работа по алгебре',
                'description' => 'Решить задачи из учебника, страницы 45-50',
                'due_at' => now()->addDays(2),
                'max_attempts' => 1,
                'visibility_scope' => 'group',
            ]);

            AssignmentTarget::create([
                'assignment_id' => $assignment->id,
                'group_id' => $group1->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            // 11. Demo Material
            $material = Material::create([
                'subject_id' => $informatics->id,
                'title' => 'Лекция: Основы программирования',
                'content' => 'Введение в программирование. Основные понятия: переменные, типы данных, операторы.',
                'visibility_scope' => 'group',
                'created_by' => $teacher2->id,
            ]);

            MaterialTarget::create([
                'material_id' => $material->id,
                'group_id' => $group1->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            // 12. Demo Chat Thread + Messages
            $chatThread = ChatThread::create([
                'type' => 'group',
                'group_id' => $group1->id,
                'subject_id' => null,
                'created_by' => $curator->id,
            ]);

            // Add members
            ChatMember::create([
                'thread_id' => $chatThread->id,
                'user_id' => $curator->id,
                'role_in_chat' => 'moderator',
            ]);
            ChatMember::create([
                'thread_id' => $chatThread->id,
                'user_id' => $student1->id,
                'role_in_chat' => 'member',
            ]);
            ChatMember::create([
                'thread_id' => $chatThread->id,
                'user_id' => $student2->id,
                'role_in_chat' => 'member',
            ]);

            // Messages
            ChatMessage::create([
                'thread_id' => $chatThread->id,
                'user_id' => $curator->id,
                'text' => 'Добро пожаловать в чат группы 2ИС-31!',
            ]);

            ChatMessage::create([
                'thread_id' => $chatThread->id,
                'user_id' => $student1->id,
                'text' => 'Спасибо! Всё понятно.',
            ]);

            // Settings
            Setting::firstOrCreate(
                ['key' => 'journal.monthly.avg2.threshold_percent'],
                ['value_json' => ['value' => 30]]
            );
            Setting::firstOrCreate(
                ['key' => 'journal.monthly.avg2.enabled'],
                ['value_json' => ['value' => true]]
            );
        });
    }
}

