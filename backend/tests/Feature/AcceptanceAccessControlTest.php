<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentTarget;
use App\Models\File;
use App\Models\Grade;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Subject;
use App\Models\TeacherSubjectGroup;
use App\Models\User;
use App\Models\UserLinkParentChild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcceptanceAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    // ==================== STUDENT RESTRICTIONS ====================

    /**
     * Test: Студент не может посмотреть assignment другой группы по URL
     */
    public function test_student_cannot_view_assignment_from_another_group_by_url(): void
    {
        // Create groups
        $groupA = Group::factory()->create(['name' => 'Group A']);
        $groupB = Group::factory()->create(['name' => 'Group B']);

        // Create students
        $studentA = User::factory()->create(['email' => 'studenta@test.local']);
        $studentB = User::factory()->create(['email' => 'studentb@test.local']);

        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $studentA->roles()->attach($studentRole->id);
        $studentB->roles()->attach($studentRole->id);

        // Add students to groups
        GroupMember::create([
            'group_id' => $groupA->id,
            'user_id' => $studentA->id,
            'role_in_group' => 'student',
        ]);

        GroupMember::create([
            'group_id' => $groupB->id,
            'user_id' => $studentB->id,
            'role_in_group' => 'student',
        ]);

        // Create subject and teacher
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $teacher = User::factory()->create(['email' => 'teacher@test.local']);
        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->first();
        $teacher->roles()->attach($teacherRole->id);

        // Create assignment for group B
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'Assignment for Group B',
            'description' => 'This assignment is only for Group B',
            'visibility_scope' => 'group',
        ]);

        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'group_id' => $groupB->id,
        ]);

        // Student A tries to access assignment by direct URL
        $response = $this->actingAs($studentA, 'api')
            ->getJson("/api/v1/assignments/{$assignment->id}");

        // Should return 404 (not visible) or 403 (forbidden)
        $this->assertContains($response->status(), [404, 403]);
    }

    /**
     * Test: Студент не может скачать файл чужой сдачи
     */
    public function test_student_cannot_download_file_from_another_student_submission(): void
    {
        // Create students
        $studentA = User::factory()->create(['email' => 'studenta@test.local']);
        $studentB = User::factory()->create(['email' => 'studentb@test.local']);

        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $studentA->roles()->attach($studentRole->id);
        $studentB->roles()->attach($studentRole->id);

        // Create subject and teacher
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $teacher = User::factory()->create(['email' => 'teacher@test.local']);
        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->first();
        $teacher->roles()->attach($teacherRole->id);

        // Create assignment
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'Test Assignment',
            'visibility_scope' => 'all',
        ]);

        // Create submission for student B
        $submissionId = DB::table('submissions')->insertGetId([
            'assignment_id' => $assignment->id,
            'student_user_id' => $studentB->id,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create file for student B's submission
        $file = File::create([
            'storage_key' => 'test/file.pdf',
            'original_name' => 'submission.pdf',
            'size' => 1024,
            'mime' => 'application/pdf',
            'uploaded_by' => $studentB->id,
        ]);

        // Attach file to submission
        DB::table('submission_files')->insert([
            'submission_id' => $submissionId,
            'file_id' => $file->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Student A tries to download file from student B's submission
        $response = $this->actingAs($studentA, 'api')
            ->getJson("/api/v1/files/{$file->id}/download");

        // Should return 403 (forbidden)
        $response->assertStatus(403);
    }

    /**
     * Test: Студент не может посмотреть чужие оценки
     */
    public function test_student_cannot_view_another_student_grades(): void
    {
        // Create students
        $studentA = User::factory()->create(['email' => 'studenta@test.local']);
        $studentB = User::factory()->create(['email' => 'studentb@test.local']);

        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $studentA->roles()->attach($studentRole->id);
        $studentB->roles()->attach($studentRole->id);

        // Create grade for student B
        $grade = Grade::create([
            'student_user_id' => $studentB->id,
            'value' => 5,
            'grade_type' => 'exam',
        ]);

        // Student A tries to view student B's grade
        $response = $this->actingAs($studentA, 'api')
            ->getJson("/api/v1/journal/grades/{$grade->id}");

        // Should return 403 (forbidden)
        $response->assertStatus(403);
    }

    /**
     * Test: Студент не может подписаться на WS каналы другой группы
     */
    public function test_student_cannot_subscribe_to_websocket_channel_of_another_group(): void
    {
        // Create groups
        $groupA = Group::factory()->create(['name' => 'Group A']);
        $groupB = Group::factory()->create(['name' => 'Group B']);

        // Create students
        $studentA = User::factory()->create(['email' => 'studenta@test.local']);
        $studentB = User::factory()->create(['email' => 'studentb@test.local']);

        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $studentA->roles()->attach($studentRole->id);
        $studentB->roles()->attach($studentRole->id);

        // Add students to groups
        GroupMember::create([
            'group_id' => $groupA->id,
            'user_id' => $studentA->id,
            'role_in_group' => 'student',
        ]);

        GroupMember::create([
            'group_id' => $groupB->id,
            'user_id' => $studentB->id,
            'role_in_group' => 'student',
        ]);

        // Student A tries to subscribe to group B's channel
        $response = $this->actingAs($studentA, 'api')
            ->postJson('/api/v1/realtime/replay', [
                'channels' => ["group.{$groupB->id}"],
            ]);

        // Should return 403 (forbidden) or reject the channel
        $this->assertContains($response->status(), [403, 400]);
    }

    // ==================== PARENT RESTRICTIONS ====================

    /**
     * Test: Родитель не может смотреть чужих детей
     */
    public function test_parent_cannot_view_another_parents_children(): void
    {
        // Create parents
        $parentA = User::factory()->create(['email' => 'parenta@test.local']);
        $parentB = User::factory()->create(['email' => 'parentb@test.local']);

        $parentRole = \App\Models\Role::where('name', 'родитель')->first();
        $parentA->roles()->attach($parentRole->id);
        $parentB->roles()->attach($parentRole->id);

        // Create children
        $childA = User::factory()->create(['email' => 'childa@test.local']);
        $childB = User::factory()->create(['email' => 'childb@test.local']);

        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $childA->roles()->attach($studentRole->id);
        $childB->roles()->attach($studentRole->id);

        // Link children to parents
        UserLinkParentChild::create([
            'parent_user_id' => $parentA->id,
            'child_user_id' => $childA->id,
        ]);

        UserLinkParentChild::create([
            'parent_user_id' => $parentB->id,
            'child_user_id' => $childB->id,
        ]);

        // Parent A tries to view child B's risks
        $response = $this->actingAs($parentA, 'api')
            ->getJson("/api/v1/analytics/risks/my?child_id={$childB->id}");

        // Should return 403 (forbidden) or empty data
        $this->assertContains($response->status(), [403, 200]);
        if ($response->status() === 200) {
            $data = $response->json('data', []);
            $this->assertEmpty($data, 'Parent should not see another parent\'s child data');
        }
    }

    /**
     * Test: Родитель не может смотреть чужие группы
     */
    public function test_parent_cannot_view_another_groups_risks(): void
    {
        // Create groups
        $groupA = Group::factory()->create(['name' => 'Group A']);
        $groupB = Group::factory()->create(['name' => 'Group B']);

        // Create parent
        $parent = User::factory()->create(['email' => 'parent@test.local']);
        $parentRole = \App\Models\Role::where('name', 'родитель')->first();
        $parent->roles()->attach($parentRole->id);

        // Create child in group A
        $child = User::factory()->create(['email' => 'child@test.local']);
        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $child->roles()->attach($studentRole->id);

        // Link child to parent
        UserLinkParentChild::create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);

        // Add child to group A
        GroupMember::create([
            'group_id' => $groupA->id,
            'user_id' => $child->id,
            'role_in_group' => 'student',
        ]);

        // Parent tries to view risks for group B
        $response = $this->actingAs($parent, 'api')
            ->getJson("/api/v1/analytics/risks?groupId={$groupB->id}");

        // Should return 403 (forbidden) or empty data
        $this->assertContains($response->status(), [403, 200]);
        if ($response->status() === 200) {
            $data = $response->json('data', []);
            $this->assertEmpty($data, 'Parent should not see another group\'s risks');
        }
    }

    // ==================== TEACHER RESTRICTIONS ====================

    /**
     * Test: Учитель не может ставить оценки группе, которую не ведёт
     */
    public function test_teacher_cannot_grade_group_they_dont_teach(): void
    {
        // Create groups
        $groupA = Group::factory()->create(['name' => 'Group A']);
        $groupB = Group::factory()->create(['name' => 'Group B']);

        // Create teachers
        $teacherA = User::factory()->create(['email' => 'teachera@test.local']);
        $teacherB = User::factory()->create(['email' => 'teacherb@test.local']);

        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->first();
        $teacherA->roles()->attach($teacherRole->id);
        $teacherB->roles()->attach($teacherRole->id);

        // Create subject
        $subject = Subject::factory()->create(['name' => 'Mathematics']);

        // Create student in group A
        $student = User::factory()->create(['email' => 'student@test.local']);
        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $student->roles()->attach($studentRole->id);

        GroupMember::create([
            'group_id' => $groupA->id,
            'user_id' => $student->id,
            'role_in_group' => 'student',
        ]);

        // Teacher A teaches group A
        TeacherSubjectGroup::create([
            'teacher_user_id' => $teacherA->id,
            'subject_id' => $subject->id,
            'group_id' => $groupA->id,
        ]);

        // Create assignment for group A
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacherA->id,
            'title' => 'Test Assignment',
            'visibility_scope' => 'group',
        ]);

        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'group_id' => $groupA->id,
        ]);

        // Create submission
        $submissionId = DB::table('submissions')->insertGetId([
            'assignment_id' => $assignment->id,
            'student_user_id' => $student->id,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Teacher B (doesn't teach group A) tries to grade the submission
        $response = $this->actingAs($teacherB, 'api')
            ->postJson("/api/v1/assignments/{$assignment->id}/submissions/{$submissionId}/grade", [
                'value' => 5,
                'comment' => 'Good work',
            ]);

        // Should return 403 (forbidden)
        $response->assertStatus(403);
    }

    /**
     * Test: Учитель не может смотреть submissions не своих групп
     */
    public function test_teacher_cannot_view_submissions_from_groups_they_dont_teach(): void
    {
        // Create groups
        $groupA = Group::factory()->create(['name' => 'Group A']);
        $groupB = Group::factory()->create(['name' => 'Group B']);

        // Create teachers
        $teacherA = User::factory()->create(['email' => 'teachera@test.local']);
        $teacherB = User::factory()->create(['email' => 'teacherb@test.local']);

        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->first();
        $teacherA->roles()->attach($teacherRole->id);
        $teacherB->roles()->attach($teacherRole->id);

        // Create subject
        $subject = Subject::factory()->create(['name' => 'Mathematics']);

        // Create students
        $studentA = User::factory()->create(['email' => 'studenta@test.local']);
        $studentB = User::factory()->create(['email' => 'studentb@test.local']);

        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $studentA->roles()->attach($studentRole->id);
        $studentB->roles()->attach($studentRole->id);

        // Add students to groups
        GroupMember::create([
            'group_id' => $groupA->id,
            'user_id' => $studentA->id,
            'role_in_group' => 'student',
        ]);

        GroupMember::create([
            'group_id' => $groupB->id,
            'user_id' => $studentB->id,
            'role_in_group' => 'student',
        ]);

        // Teacher A teaches group A
        TeacherSubjectGroup::create([
            'teacher_user_id' => $teacherA->id,
            'subject_id' => $subject->id,
            'group_id' => $groupA->id,
        ]);

        // Teacher B teaches group B
        TeacherSubjectGroup::create([
            'teacher_user_id' => $teacherB->id,
            'subject_id' => $subject->id,
            'group_id' => $groupB->id,
        ]);

        // Create assignment for group B
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacherB->id,
            'title' => 'Assignment for Group B',
            'visibility_scope' => 'group',
        ]);

        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'group_id' => $groupB->id,
        ]);

        // Create submission for student B
        DB::table('submissions')->insert([
            'assignment_id' => $assignment->id,
            'student_user_id' => $studentB->id,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Teacher A (doesn't teach group B) tries to view submission
        $response = $this->actingAs($teacherA, 'api')
            ->getJson("/api/v1/assignments/{$assignment->id}");

        // Should return 404 (not visible) or 403 (forbidden)
        $this->assertContains($response->status(), [404, 403]);
    }

    // ==================== ADMIN PERMISSIONS ====================

    /**
     * Test: Admin может всё
     */
    public function test_admin_can_access_all_resources(): void
    {
        // Create admin
        $admin = User::factory()->create(['email' => 'admin@test.local']);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $admin->roles()->attach($adminRole->id);

        // Create group
        $group = Group::factory()->create(['name' => 'Test Group']);

        // Create student
        $student = User::factory()->create(['email' => 'student@test.local']);
        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $student->roles()->attach($studentRole->id);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $student->id,
            'role_in_group' => 'student',
        ]);

        // Create subject and teacher
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $teacher = User::factory()->create(['email' => 'teacher@test.local']);
        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->first();
        $teacher->roles()->attach($teacherRole->id);

        // Create assignment
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'Test Assignment',
            'visibility_scope' => 'group',
        ]);

        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'group_id' => $group->id,
        ]);

        // Create grade
        $grade = Grade::create([
            'student_user_id' => $student->id,
            'value' => 5,
            'grade_type' => 'exam',
        ]);

        // Admin can view assignment
        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/v1/assignments/{$assignment->id}");
        $response->assertStatus(200);

        // Admin can view grade
        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/v1/journal/grades/{$grade->id}");
        $response->assertStatus(200);

        // Admin can view group risks
        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/v1/analytics/risks?groupId={$group->id}");
        $response->assertStatus(200);

        // Admin can subscribe to any WebSocket channel
        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/realtime/replay', [
                'channels' => ["group.{$group->id}"],
            ]);
        $this->assertContains($response->status(), [200, 201]);
    }
}

