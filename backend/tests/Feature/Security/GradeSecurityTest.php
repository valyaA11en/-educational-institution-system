<?php

namespace Tests\Feature\Security;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\ScheduleItem;
use App\Models\User;
use App\Models\UserLinkParentChild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    /**
     * Test: студент не может GET grades другого студента
     */
    public function test_student_cannot_get_grades_of_another_student(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        // Create two students
        $student1 = User::factory()->create(['tenant_id' => $tenant->id]);
        $student2 = User::factory()->create(['tenant_id' => $tenant->id]);
        $studentRole = \App\Models\Role::where('name', 'студент')->firstOrCreate(['name' => 'студент']);
        $student1->roles()->attach($studentRole->id);
        $student2->roles()->attach($studentRole->id);

        // Create schedule item and lesson
        $scheduleItem = ScheduleItem::factory()->create(['tenant_id' => $tenant->id]);
        $lesson = Lesson::factory()->create([
            'tenant_id' => $tenant->id,
            'schedule_item_id' => $scheduleItem->id,
        ]);

        // Create grade for student2
        $grade = Grade::create([
            'lesson_id' => $lesson->id,
            'student_user_id' => $student2->id,
            'value' => 5,
        ]);

        // Student1 tries to get grades (which should be filtered, but we test direct access attempt)
        // Since /api/journal/grades returns all grades for the user's context, we test that
        // student1 cannot see student2's grades in the response
        $token = $this->loginAs('студент', $tenant, $student1);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/journal/grades');

        $response->assertStatus(200);
        
        // Verify that student2's grade is not in the response
        $grades = $response->json('data') ?? $response->json();
        if (is_array($grades)) {
            $gradeIds = array_column($grades, 'id');
            $this->assertNotContains($grade->id, $gradeIds, 'Student1 should not see student2\'s grades');
        }
    }

    /**
     * Test: родитель не может посмотреть чужого ребёнка
     */
    public function test_parent_cannot_get_grades_of_another_student(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        // Create parent and two students
        $parent = User::factory()->create(['tenant_id' => $tenant->id]);
        $student1 = User::factory()->create(['tenant_id' => $tenant->id]);
        $student2 = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $parentRole = \App\Models\Role::where('name', 'родитель')->firstOrCreate(['name' => 'родитель']);
        $studentRole = \App\Models\Role::where('name', 'студент')->firstOrCreate(['name' => 'студент']);
        $parent->roles()->attach($parentRole->id);
        $student1->roles()->attach($studentRole->id);
        $student2->roles()->attach($studentRole->id);

        // Link parent to student1 (not student2)
        UserLinkParentChild::create([
            'parent_user_id' => $parent->id,
            'student_user_id' => $student1->id,
            'status' => 'approved',
            'link_code_hash' => 'hash123',
        ]);

        // Create schedule item and lesson
        $scheduleItem = ScheduleItem::factory()->create(['tenant_id' => $tenant->id]);
        $lesson = Lesson::factory()->create([
            'tenant_id' => $tenant->id,
            'schedule_item_id' => $scheduleItem->id,
        ]);

        // Create grade for student2 (not linked to parent)
        $grade = Grade::create([
            'lesson_id' => $lesson->id,
            'student_user_id' => $student2->id,
            'value' => 5,
        ]);

        // Parent tries to get grades
        // Since parent is linked to student1, they should not see student2's grades
        $token = $this->loginAs('родитель', $tenant, $parent);
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/journal/grades');

        $response->assertStatus(200);
        
        // Verify that student2's grade is not in the response
        $grades = $response->json('data') ?? $response->json();
        if (is_array($grades)) {
            $gradeIds = array_column($grades, 'id');
            $this->assertNotContains($grade->id, $gradeIds, 'Parent should not see grades of unlinked student');
        }
    }
}


