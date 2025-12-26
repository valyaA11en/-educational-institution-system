<?php

namespace Tests\Feature;

use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_publish_version_archives_previous_published(): void
    {
        $user = User::factory()->create();
        $term = Term::factory()->create();

        // Create first published version
        $version1 = ScheduleVersion::create([
            'term_id' => $term->id,
            'status' => 'published',
            'created_by' => $user->id,
            'published_at' => now()->subDay(),
        ]);

        // Create draft version
        $version2 = ScheduleVersion::create([
            'term_id' => $term->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        // Publish version2
        $response = $this->actingAs($user, 'api')
            ->postJson("/api/v1/schedule/versions/{$version2->id}/publish");

        $response->assertStatus(200);

        // Check that version1 is archived
        $version1->refresh();
        $this->assertEquals('archived', $version1->status);

        // Check that version2 is published
        $version2->refresh();
        $this->assertEquals('published', $version2->status);
        $this->assertNotNull($version2->published_at);
    }

    public function test_create_item_logs_to_changelog(): void
    {
        $user = User::factory()->create();
        $term = Term::factory()->create();
        $version = ScheduleVersion::create([
            'term_id' => $term->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        // This test would require full controller setup, so we'll test the service directly
        $versionService = app(\App\Services\Schedule\ScheduleVersionService::class);
        
        $item = ScheduleItem::factory()->create([
            'version_id' => $version->id,
        ]);

        $versionService->logItemChange($version->id, 'create', $item, $user->id, null, $item->toArray());

        // Check changelog entry exists
        $this->assertDatabaseHas('schedule_change_log', [
            'version_id' => $version->id,
            'schedule_item_id' => $item->id,
            'action' => 'create',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_get_changes_returns_filtered_logs(): void
    {
        $user = User::factory()->create();
        $term = Term::factory()->create();
        $version = ScheduleVersion::create([
            'term_id' => $term->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        // Create changelog entries
        \App\Models\ScheduleChangelog::create([
            'version_id' => $version->id,
            'action' => 'create',
            'actor_user_id' => $user->id,
            'before_json' => null,
            'after_json' => ['test' => 'data'],
        ]);

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/v1/schedule/changes?versionId={$version->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_diff_returns_added_removed_changed(): void
    {
        $user = User::factory()->create();
        $term = Term::factory()->create();
        $version1 = ScheduleVersion::create([
            'term_id' => $term->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $version2 = ScheduleVersion::create([
            'term_id' => $term->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        // Create items in version1
        $item1 = ScheduleItem::factory()->create([
            'version_id' => $version1->id,
            'date' => '2024-01-01',
            'time_slot_id' => 1,
            'group_id' => 1,
            'subgroup_id' => null,
            'subject_id' => 1,
        ]);

        // Create different items in version2
        $item2 = ScheduleItem::factory()->create([
            'version_id' => $version2->id,
            'date' => '2024-01-01',
            'time_slot_id' => 1,
            'group_id' => 1,
            'subgroup_id' => null,
            'subject_id' => 1,
            'teacher_user_id' => 999, // Different teacher
        ]);

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/v1/schedule/diff?fromVersionId={$version1->id}&toVersionId={$version2->id}");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('added', $data);
        $this->assertArrayHasKey('removed', $data);
        $this->assertArrayHasKey('changed', $data);
    }
}

