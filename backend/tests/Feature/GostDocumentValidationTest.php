<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GostDocumentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;
    protected DocTemplate $orderTemplate;
    protected DocTemplate $decisionTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
        ]);

        // Create user
        $this->user = User::create([
            'fio' => 'Test User',
            'email' => 'test@example.com',
            'password_hash' => Hash::make('password'),
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        // Create templates
        $this->orderTemplate = DocTemplate::create([
            'type' => 'order',
            'name' => 'order_gost_default',
            'file_template_key' => 'order_gost_default.docx',
            'schema_json' => [],
        ]);

        $this->decisionTemplate = DocTemplate::create([
            'type' => 'decision',
            'name' => 'decision_gost_default',
            'file_template_key' => 'decision_gost_default.docx',
            'schema_json' => [],
        ]);
    }

    /**
     * Test creating order without basis -> 422
     */
    public function test_create_order_without_basis_returns_422(): void
    {
        $token = JWTAuth::fromUser($this->user);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/documents', [
                'type' => 'order',
                'template_id' => $this->orderTemplate->id,
                'data_json' => [
                    'org_name' => 'Test Organization',
                    'title' => 'Test Order',
                    // 'basis' => 'Missing basis', // Intentionally missing
                    'body_items' => [
                        ['no' => '1', 'text' => 'First item'],
                    ],
                    'signer_name' => 'Test Signer',
                    'signer_role' => 'Director',
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data_json']);
    }

    /**
     * Test creating order with all required fields -> 201
     */
    public function test_create_order_with_all_required_fields_returns_201(): void
    {
        $token = JWTAuth::fromUser($this->user);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/documents', [
                'type' => 'order',
                'template_id' => $this->orderTemplate->id,
                'data_json' => [
                    'org_name' => 'Test Organization',
                    'title' => 'Test Order',
                    'basis' => 'Test basis',
                    'body_items' => [
                        ['no' => '1', 'text' => 'First item'],
                        ['no' => '2', 'text' => 'Second item'],
                    ],
                    'signer_name' => 'Test Signer',
                    'signer_role' => 'Director',
                ],
            ]);

        $response->assertStatus(201);
    }

    /**
     * Test creating decision without basis -> 422
     */
    public function test_create_decision_without_basis_returns_422(): void
    {
        $token = JWTAuth::fromUser($this->user);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/documents', [
                'type' => 'decision',
                'template_id' => $this->decisionTemplate->id,
                'data_json' => [
                    'org_name' => 'Test Organization',
                    'title' => 'Test Decision',
                    // 'basis' => 'Missing basis', // Intentionally missing
                    'body_items' => [
                        ['no' => '1', 'text' => 'First item'],
                    ],
                    'signer_name' => 'Test Signer',
                    'signer_role' => 'Director',
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data_json']);
    }

    /**
     * Test validation endpoint returns errors
     */
    public function test_validate_document_endpoint_returns_errors(): void
    {
        $token = JWTAuth::fromUser($this->user);

        // Create document with missing basis
        $document = Document::create([
            'type' => 'order',
            'number' => '1',
            'date' => now(),
            'status' => 'draft',
            'template_id' => $this->orderTemplate->id,
            'created_by' => $this->user->id,
            'data_json' => [
                'org_name' => 'Test Organization',
                'title' => 'Test Order',
                // 'basis' => 'Missing basis', // Intentionally missing
                'body_items' => [
                    ['no' => '1', 'text' => 'First item'],
                ],
                'signer_name' => 'Test Signer',
                'signer_role' => 'Director',
            ],
            'verify_hash' => hash('sha256', uniqid('doc_', true) . time()),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/documents/{$document->id}/gost/validate");

        $response->assertStatus(200);
        $response->assertJson([
            'valid' => false,
        ]);
        $this->assertNotEmpty($response->json('errors'));
    }

    /**
     * Test validation endpoint returns valid for correct document
     */
    public function test_validate_document_endpoint_returns_valid(): void
    {
        $token = JWTAuth::fromUser($this->user);

        // Create document with all required fields
        $document = Document::create([
            'type' => 'order',
            'number' => '1',
            'date' => now(),
            'status' => 'draft',
            'template_id' => $this->orderTemplate->id,
            'created_by' => $this->user->id,
            'data_json' => [
                'org_name' => 'Test Organization',
                'title' => 'Test Order',
                'basis' => 'Test basis',
                'body_items' => [
                    ['no' => '1', 'text' => 'First item'],
                ],
                'signer_name' => 'Test Signer',
                'signer_role' => 'Director',
            ],
            'verify_hash' => hash('sha256', uniqid('doc_', true) . time()),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/documents/{$document->id}/gost/validate");

        $response->assertStatus(200);
        $response->assertJson([
            'valid' => true,
            'errors' => [],
        ]);
    }
}

