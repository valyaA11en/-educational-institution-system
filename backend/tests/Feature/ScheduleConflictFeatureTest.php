<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleConflictFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_create_conflict_then_force_save(): void
    {
        $this->markTestIncomplete('TODO: implement ScheduleController::createItem with conflict detection and force save logic');
    }
}


