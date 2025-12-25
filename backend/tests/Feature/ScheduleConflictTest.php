<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Room;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_schedule_item_with_room_conflict_returns_409(): void
    {
        // Create roles and permissions first
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Create test data
        $term = DB::table('terms')->insertGetId([
            'academic_year_id' => \DB::table('academic_years')->insertGetId([
                'name' => '2024-2025',
                'start_date' => '2024-09-01',
                'end_date' => '2025-08-31',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'name' => 'Осенний семестр',
            'start_date' => '2024-09-01',
            'end_date' => '2024-12-31',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $version = ScheduleVersion::create([
            'term_id' => $term,
            'status' => 'draft',
            'created_by' => 1,
        ]);

        $group = Group::create(['name' => 'Test Group', 'code' => 'TG']);
        $subject = Subject::create(['name' => 'Math', 'code' => 'MATH']);
        $room = Room::create(['name' => '101', 'code' => '101', 'capacity' => 30]);
        $timeSlot = TimeSlot::create([
            'name' => '1',
            'start_time' => '08:00',
            'end_time' => '09:30',
            'order' => 1,
        ]);

        $teacher1 = User::create([
            'fio' => 'Teacher 1',
            'email' => 'teacher1@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        $teacher2 = User::create([
            'fio' => 'Teacher 2',
            'email' => 'teacher2@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        // Create first schedule item
        $firstItem = ScheduleItem::create([
            'version_id' => $version->id,
            'date' => '2024-09-15',
            'time_slot_id' => $timeSlot->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher1->id,
            'room_id' => $room->id,
            'created_by' => 1,
        ]);

        // Create user and authenticate
        $user = User::create([
            'fio' => 'Test User',
            'email' => 'test@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync([DB::table('roles')->where('name', 'admin')->value('id')]);

        // Get JWT token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('access_token');

        // Try to create second item with same room and time slot
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/v1/schedule/items', [
            'date' => '2024-09-15',
            'time_slot_id' => $timeSlot->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher2->id,
            'room_id' => $room->id,
            'version_id' => $version->id,
        ]);

        $response->assertStatus(409);
        $response->assertJsonStructure([
            'message',
            'conflicts' => [
                '*' => [
                    'type',
                    'entityId',
                    'message',
                    'conflictingItemId',
                    'details',
                ],
            ],
        ]);

        $conflicts = $response->json('conflicts');
        $this->assertNotEmpty($conflicts);
        $this->assertEquals('room', $conflicts[0]['type']);
        $this->assertEquals($room->id, $conflicts[0]['entityId']);
    }

    public function test_create_schedule_item_with_force_and_reason_returns_201(): void
    {
        // Create roles and permissions first
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Create test data
        $term = DB::table('terms')->insertGetId([
            'academic_year_id' => \DB::table('academic_years')->insertGetId([
                'name' => '2024-2025',
                'start_date' => '2024-09-01',
                'end_date' => '2025-08-31',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'name' => 'Осенний семестр',
            'start_date' => '2024-09-01',
            'end_date' => '2024-12-31',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $version = ScheduleVersion::create([
            'term_id' => $term,
            'status' => 'draft',
            'created_by' => 1,
        ]);

        $group = Group::create(['name' => 'Test Group', 'code' => 'TG']);
        $subject = Subject::create(['name' => 'Math', 'code' => 'MATH']);
        $room = Room::create(['name' => '101', 'code' => '101', 'capacity' => 30]);
        $timeSlot = TimeSlot::create([
            'name' => '1',
            'start_time' => '08:00',
            'end_time' => '09:30',
            'order' => 1,
        ]);

        $teacher1 = User::create([
            'fio' => 'Teacher 1',
            'email' => 'teacher1@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        $teacher2 = User::create([
            'fio' => 'Teacher 2',
            'email' => 'teacher2@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        // Create first schedule item
        $firstItem = ScheduleItem::create([
            'version_id' => $version->id,
            'date' => '2024-09-15',
            'time_slot_id' => $timeSlot->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher1->id,
            'room_id' => $room->id,
            'created_by' => 1,
        ]);

        // Create user and authenticate
        $user = User::create([
            'fio' => 'Test User',
            'email' => 'test2@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->roles()->sync([DB::table('roles')->where('name', 'admin')->value('id')]);

        // Get JWT token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('access_token');

        // Create second item with force=true and override_reason
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/v1/schedule/items', [
            'date' => '2024-09-15',
            'time_slot_id' => $timeSlot->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher2->id,
            'room_id' => $room->id,
            'version_id' => $version->id,
            'force' => true,
            'override_reason' => 'Emergency replacement',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'version_id',
            'date',
            'time_slot_id',
            'group_id',
            'subject_id',
            'teacher_user_id',
            'room_id',
            'override_reason',
        ]);

        $data = $response->json();
        $this->assertEquals('Emergency replacement', $data['override_reason']);

        // Verify item was created
        $this->assertDatabaseHas('schedule_items', [
            'id' => $data['id'],
            'override_reason' => 'Emergency replacement',
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_log', [
            'entity' => 'schedule_item',
            'entity_id' => $data['id'],
            'action' => 'schedule.force_override',
        ]);

        // Verify outbox event was created
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'schedule.changed',
            'entity_type' => 'schedule_item',
            'entity_id' => $data['id'],
        ]);
    }
}

