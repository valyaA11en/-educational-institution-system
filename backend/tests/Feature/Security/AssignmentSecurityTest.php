<?php

namespace Tests\Feature\Security;

use App\Models\Assignment;
use App\Models\AssignmentTarget;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    /**
     * Test: студент группы A не может GET /api/assignments/{id} группы B
     */
    public function test_student_from_group_a_cannot_get_assignment_from_group_b(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $groupA = Group::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Group A']);
        $groupB = Group::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Group B']);

        // Create students
        $studentA = User::factory()->create(['tenant_id' => $tenant->id]);
        $studentB = User::factory()->create(['tenant_id' => $tenant->id]);
        $studentRole = \App\Models\Role::where('name', 'студент')->firstOrCreate(['name' => 'студент']);
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
        $teacher = User::factory()->create(['tenant_id' => $tenant->id]);
        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->firstOrCreate(['name' => 'преподаватель']);
        $teacher->roles()->attach($teacherRole->id);

        // Create subject
        $subject = Subject::factory()->create(['tenant_id' => $tenant->id]);

        // Create assignment for group B
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'Assignment for Group B',
            'description' => 'This assignment is only for Group B',
            'visibility_scope' => 'group',
            'tenant_id' => $tenant->id,
        ]);

        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'group_id' => $groupB->id,
        ]);

        // Student A tries to access assignment from group B
        $token = $this->loginAs('студент', $tenant, $studentA);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/assignments/{$assignment->id}");

        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404]);
    }

    /**
     * Test: студент группы A не может submit assignment группы B
     */
    public function test_student_from_group_a_cannot_submit_assignment_from_group_b(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $groupA = Group::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Group A']);
        $groupB = Group::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Group B']);

        // Create student
        $studentA = User::factory()->create(['tenant_id' => $tenant->id]);
        $studentRole = \App\Models\Role::where('name', 'студент')->firstOrCreate(['name' => 'студент']);
        $studentA->roles()->attach($studentRole->id);

        // Add student to group A
        GroupMember::create([
            'group_id' => $groupA->id,
            'user_id' => $studentA->id,
            'role_in_group' => 'student',
        ]);

        // Create teacher
        $teacher = User::factory()->create(['tenant_id' => $tenant->id]);
        $teacherRole = \App\Models\Role::where('name', 'преподаватель')->firstOrCreate(['name' => 'преподаватель']);
        $teacher->roles()->attach($teacherRole->id);

        // Create subject
        $subject = Subject::factory()->create(['tenant_id' => $tenant->id]);

        // Create assignment for group B
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'teacher_user_id' => $teacher->id,
            'title' => 'Assignment for Group B',
            'description' => 'This assignment is only for Group B',
            'visibility_scope' => 'group',
            'tenant_id' => $tenant->id,
        ]);

        AssignmentTarget::create([
            'assignment_id' => $assignment->id,
            'group_id' => $groupB->id,
        ]);

        // Student A tries to submit assignment from group B
        $token = $this->loginAs('студент', $tenant, $studentA);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/assignments/{$assignment->id}/submit", []);

        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404]);
    }
}


