<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ChatThread;
use App\Models\Document;
use App\Models\File;
use App\Models\Grade;
use App\Models\Material;
use App\Models\ScheduleItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAccessDeniedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_student_cannot_view_other_student_schedule_item(): void
    {
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();
        $group = \App\Models\Group::factory()->create();
        
        // Add students to different groups
        \DB::table('group_members')->insert([
            'group_id' => $group->id,
            'user_id' => $student1->id,
            'role_in_group' => 'student',
        ]);

        $scheduleItem = ScheduleItem::factory()->create(['group_id' => $group->id]);

        $this->actingAs($student2, 'api')
            ->getJson("/api/schedule/items/{$scheduleItem->id}")
            ->assertStatus(403);
    }

    public function test_student_cannot_view_other_student_grade(): void
    {
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        $grade = Grade::factory()->create(['student_user_id' => $student1->id]);

        $this->actingAs($student2, 'api')
            ->getJson("/api/journal/grades/{$grade->id}")
            ->assertStatus(403);
    }

    public function test_student_cannot_submit_assignment_not_targeted_to_them(): void
    {
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();
        $teacher = User::factory()->create();
        $group = \App\Models\Group::factory()->create();
        $subject = \App\Models\Subject::factory()->create();

        \DB::table('group_members')->insert([
            'group_id' => $group->id,
            'user_id' => $student1->id,
            'role_in_group' => 'student',
        ]);

        $assignment = Assignment::factory()->create([
            'teacher_user_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        \DB::table('assignment_targets')->insert([
            'assignment_id' => $assignment->id,
            'group_id' => $group->id,
        ]);

        $this->actingAs($student2, 'api')
            ->postJson("/api/assignments/{$assignment->id}/submit")
            ->assertStatus(403);
    }

    public function test_teacher_cannot_grade_assignment_not_assigned_to_them(): void
    {
        $teacher1 = User::factory()->create();
        $teacher2 = User::factory()->create();
        $group = \App\Models\Group::factory()->create();
        $subject = \App\Models\Subject::factory()->create();

        \DB::table('teacher_subject_group')->insert([
            'teacher_user_id' => $teacher1->id,
            'subject_id' => $subject->id,
            'group_id' => $group->id,
        ]);

        $assignment = Assignment::factory()->create([
            'teacher_user_id' => $teacher1->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($teacher2, 'api')
            ->postJson("/api/assignments/{$assignment->id}/grade")
            ->assertStatus(403);
    }

    public function test_user_cannot_view_document_not_in_route(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson("/api/documents/{$document->id}")
            ->assertStatus(403);
    }

    public function test_user_cannot_view_chat_thread_not_member(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson("/api/chats/threads/{$thread->id}/messages")
            ->assertStatus(403);
    }

    public function test_user_cannot_download_file_without_access(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->create(['uploaded_by' => User::factory()->create()->id]);

        $this->actingAs($user, 'api')
            ->getJson("/api/files/{$file->id}/download")
            ->assertStatus(403);
    }

    public function test_user_cannot_update_ticket_not_assigned(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'created_by' => User::factory()->create()->id,
            'assigned_to' => User::factory()->create()->id,
        ]);

        $this->actingAs($user, 'api')
            ->putJson("/api/tickets/{$ticket->id}")
            ->assertStatus(403);
    }
}


