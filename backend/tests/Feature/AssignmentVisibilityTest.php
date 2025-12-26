<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentTarget;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_student_from_group_a_cannot_see_assignment_from_group_b_even_by_direct_link(): void
    {
        // Create groups
        $groupA = Group::factory()->create(['name' => 'Group A']);
        $groupB = Group::factory()->create(['name' => 'Group B']);

        // Create subject
        $subject = Subject::factory()->create(['name' => 'Mathematics']);

        // Create students
        $studentA = User::factory()->create(['email' => 'studenta@test.local']);
        $studentB = User::factory()->create(['email' => 'studentb@test.local']);

        // Assign roles
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

        // Create teacher
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

        // Add target for group B
        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'group_id' => $groupB->id,
        ]);

        // Student A tries to access assignment by direct link
        $response = $this->actingAs($studentA, 'api')
            ->getJson("/api/assignments/{$assignment->id}");

        // Should return 404 (not visible) or 403 (forbidden)
        $this->assertContains($response->status(), [404, 403]);
    }

    public function test_student_can_see_assignment_from_their_group(): void
    {
        // Create group
        $group = Group::factory()->create(['name' => 'Group A']);

        // Create subject
        $subject = Subject::factory()->create(['name' => 'Mathematics']);

        // Create student
        $student = User::factory()->create(['email' => 'student@test.local']);
        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $student->roles()->attach($studentRole->id);

        // Add student to group
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $student->id,
            'role_in_group' => 'student',
        ]);

        // Create teacher
        $teacher = User::factory()->create(['email' => 'teacher@test.local']);
        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->first();
        $teacher->roles()->attach($teacherRole->id);

        // Create assignment for group
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'Assignment for Group A',
            'description' => 'This assignment is for Group A',
            'visibility_scope' => 'group',
        ]);

        // Add target for group
        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'group_id' => $group->id,
        ]);

        // Student can access assignment
        $response = $this->actingAs($student, 'api')
            ->getJson("/api/assignments/{$assignment->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $assignment->id,
            'title' => 'Assignment for Group A',
        ]);
    }

    public function test_student_can_see_individual_assignment_targeted_to_them(): void
    {
        // Create subject
        $subject = Subject::factory()->create(['name' => 'Mathematics']);

        // Create student
        $student = User::factory()->create(['email' => 'student@test.local']);
        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $student->roles()->attach($studentRole->id);

        // Create teacher
        $teacher = User::factory()->create(['email' => 'teacher@test.local']);
        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->first();
        $teacher->roles()->attach($teacherRole->id);

        // Create individual assignment
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'Individual Assignment',
            'description' => 'This assignment is for specific student',
            'visibility_scope' => 'individual',
        ]);

        // Add target for student
        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'student_user_id' => $student->id,
        ]);

        // Student can access assignment
        $response = $this->actingAs($student, 'api')
            ->getJson("/api/assignments/{$assignment->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $assignment->id,
            'title' => 'Individual Assignment',
        ]);
    }

    public function test_student_cannot_see_individual_assignment_targeted_to_another_student(): void
    {
        // Create subject
        $subject = Subject::factory()->create(['name' => 'Mathematics']);

        // Create students
        $studentA = User::factory()->create(['email' => 'studenta@test.local']);
        $studentB = User::factory()->create(['email' => 'studentb@test.local']);

        $studentRole = \App\Models\Role::where('name', 'студент')->first();
        $studentA->roles()->attach($studentRole->id);
        $studentB->roles()->attach($studentRole->id);

        // Create teacher
        $teacher = User::factory()->create(['email' => 'teacher@test.local']);
        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->first();
        $teacher->roles()->attach($teacherRole->id);

        // Create individual assignment for student B
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'Individual Assignment for Student B',
            'description' => 'This assignment is for Student B only',
            'visibility_scope' => 'individual',
        ]);

        // Add target for student B
        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'student_user_id' => $studentB->id,
        ]);

        // Student A tries to access assignment
        $response = $this->actingAs($studentA, 'api')
            ->getJson("/api/assignments/{$assignment->id}");

        // Should return 404 (not visible) or 403 (forbidden)
        $this->assertContains($response->status(), [404, 403]);
    }
}


