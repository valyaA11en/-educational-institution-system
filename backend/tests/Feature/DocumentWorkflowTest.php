<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentRoute;
use App\Models\DocTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_send_to_approval_creates_routes_and_changes_status(): void
    {
        $user = User::factory()->create();
        $approverRole = Role::where('name', 'преподаватель')->first();
        $approverUser = User::factory()->create();

        $template = DocTemplate::create([
            'type' => 'order',
            'name' => 'Test Template',
            'schema_json' => [],
        ]);

        $document = Document::create([
            'type' => 'order',
            'number' => '',
            'date' => now()->toDateString(),
            'status' => 'draft',
            'template_id' => $template->id,
            'data_json' => ['test' => 'data'],
            'created_by' => $user->id,
            'verify_hash' => 'test-hash',
        ]);

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/v1/documents/{$document->id}/send-to-approval", [
                'route' => [
                    ['stepNo' => 1, 'approverRoleId' => $approverRole->id],
                    ['stepNo' => 2, 'approverUserId' => $approverUser->id],
                ],
            ]);

        $response->assertStatus(200);
        $document->refresh();
        $this->assertEquals('on_review', $document->status);
        $this->assertEquals(2, $document->routes()->count());
    }

    public function test_approve_step_updates_status_and_moves_to_next(): void
    {
        $user = User::factory()->create();
        $approverRole = Role::where('name', 'преподаватель')->first();
        $approverUser = User::factory()->create();
        $approverUser->roles()->attach($approverRole->id);

        $template = DocTemplate::create([
            'type' => 'order',
            'name' => 'Test Template',
            'schema_json' => [],
        ]);

        $document = Document::create([
            'type' => 'order',
            'number' => '',
            'date' => now()->toDateString(),
            'status' => 'on_review',
            'template_id' => $template->id,
            'data_json' => ['test' => 'data'],
            'created_by' => $user->id,
            'verify_hash' => 'test-hash',
        ]);

        DocumentRoute::create([
            'document_id' => $document->id,
            'step_no' => 1,
            'approver_role_id' => $approverRole->id,
            'status' => 'pending',
        ]);

        DocumentRoute::create([
            'document_id' => $document->id,
            'step_no' => 2,
            'approver_user_id' => $approverUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($approverUser, 'api')
            ->postJson("/api/v1/documents/{$document->id}/approve", [
                'comment' => 'Approved',
            ]);

        $response->assertStatus(200);
        $document->refresh();
        $this->assertEquals('on_review', $document->status); // Still on review, next step pending

        $step1 = DocumentRoute::where('document_id', $document->id)
            ->where('step_no', 1)
            ->first();
        $this->assertEquals('approved', $step1->status);
    }

    public function test_approve_last_step_sets_document_to_approved(): void
    {
        $user = User::factory()->create();
        $approverRole = Role::where('name', 'преподаватель')->first();
        $approverUser = User::factory()->create();
        $approverUser->roles()->attach($approverRole->id);

        $template = DocTemplate::create([
            'type' => 'order',
            'name' => 'Test Template',
            'schema_json' => [],
        ]);

        $document = Document::create([
            'type' => 'order',
            'number' => '',
            'date' => now()->toDateString(),
            'status' => 'on_review',
            'template_id' => $template->id,
            'data_json' => ['test' => 'data'],
            'created_by' => $user->id,
            'verify_hash' => 'test-hash',
        ]);

        DocumentRoute::create([
            'document_id' => $document->id,
            'step_no' => 1,
            'approver_role_id' => $approverRole->id,
            'status' => 'approved',
        ]);

        DocumentRoute::create([
            'document_id' => $document->id,
            'step_no' => 2,
            'approver_user_id' => $approverUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($approverUser, 'api')
            ->postJson("/api/v1/documents/{$document->id}/approve");

        $response->assertStatus(200);
        $document->refresh();
        $this->assertEquals('approved', $document->status);
    }

    public function test_reject_step_sets_document_to_draft(): void
    {
        $user = User::factory()->create();
        $approverRole = Role::where('name', 'преподаватель')->first();
        $approverUser = User::factory()->create();
        $approverUser->roles()->attach($approverRole->id);

        $template = DocTemplate::create([
            'type' => 'order',
            'name' => 'Test Template',
            'schema_json' => [],
        ]);

        $document = Document::create([
            'type' => 'order',
            'number' => '',
            'date' => now()->toDateString(),
            'status' => 'on_review',
            'template_id' => $template->id,
            'data_json' => ['test' => 'data'],
            'created_by' => $user->id,
            'verify_hash' => 'test-hash',
        ]);

        DocumentRoute::create([
            'document_id' => $document->id,
            'step_no' => 1,
            'approver_role_id' => $approverRole->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($approverUser, 'api')
            ->postJson("/api/v1/documents/{$document->id}/reject", [
                'comment' => 'Rejected due to errors',
            ]);

        $response->assertStatus(200);
        $document->refresh();
        $this->assertEquals('draft', $document->status);

        $step1 = DocumentRoute::where('document_id', $document->id)
            ->where('step_no', 1)
            ->first();
        $this->assertEquals('rejected', $step1->status);
        $this->assertEquals('Rejected due to errors', $step1->comment);
    }

    public function test_sign_document_sets_status_and_signer(): void
    {
        $user = User::factory()->create();
        $signer = User::factory()->create();
        $signerRole = Role::where('name', 'admin')->first();
        $signer->roles()->attach($signerRole->id);

        $template = DocTemplate::create([
            'type' => 'order',
            'name' => 'Test Template',
            'schema_json' => [],
        ]);

        $document = Document::create([
            'type' => 'order',
            'number' => '1',
            'date' => now()->toDateString(),
            'status' => 'approved',
            'template_id' => $template->id,
            'data_json' => ['test' => 'data'],
            'created_by' => $user->id,
            'verify_hash' => 'test-hash',
        ]);

        $response = $this->actingAs($signer, 'api')
            ->postJson("/api/v1/documents/{$document->id}/sign", [
                'comment' => 'Signed',
            ]);

        $response->assertStatus(200);
        $document->refresh();
        $this->assertEquals('signed', $document->status);
        $this->assertEquals($signer->id, $document->signed_by);
        $this->assertNotNull($document->signed_at);
    }

    public function test_register_number_assigns_number_from_registry(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $admin->roles()->attach($adminRole->id);

        $template = DocTemplate::create([
            'type' => 'order',
            'name' => 'Test Template',
            'schema_json' => [],
        ]);

        $document = Document::create([
            'type' => 'order',
            'number' => '',
            'date' => now()->toDateString(),
            'status' => 'draft',
            'template_id' => $template->id,
            'data_json' => ['test' => 'data'],
            'created_by' => $user->id,
            'verify_hash' => 'test-hash',
        ]);

        $response = $this->actingAs($admin, 'api')
            ->postJson("/api/v1/documents/{$document->id}/register-number");

        $response->assertStatus(200);
        $document->refresh();
        $this->assertEquals('1', $document->number);
        $this->assertNotNull($document->date);
    }
}









