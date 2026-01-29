<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for schedule conflict checks (if not already covered)
        Schema::table('schedule_items', function (Blueprint $table): void {
            // Composite index for group conflict checks (date, time_slot_id, group_id, subgroup_id)
            if (!$this->indexExists('schedule_items', 'idx_schedule_items_group_conflict')) {
                $table->index(['date', 'time_slot_id', 'group_id', 'subgroup_id'], 'idx_schedule_items_group_conflict');
            }
        });

        // Add indexes for visible scopes (assignment_targets)
        Schema::table('assignment_targets', function (Blueprint $table): void {
            // Index for student_user_id lookups
            if (!$this->indexExists('assignment_targets', 'idx_assignment_targets_student')) {
                $table->index('student_user_id', 'idx_assignment_targets_student');
            }
            // Index for group_id lookups
            if (!$this->indexExists('assignment_targets', 'idx_assignment_targets_group')) {
                $table->index('group_id', 'idx_assignment_targets_group');
            }
            // Index for subgroup_id lookups
            if (!$this->indexExists('assignment_targets', 'idx_assignment_targets_subgroup')) {
                $table->index('subgroup_id', 'idx_assignment_targets_subgroup');
            }
        });

        // Add indexes for visible scopes (material_targets)
        Schema::table('material_targets', function (Blueprint $table): void {
            // Index for student_user_id lookups
            if (!$this->indexExists('material_targets', 'idx_material_targets_student')) {
                $table->index('student_user_id', 'idx_material_targets_student');
            }
            // Index for group_id lookups
            if (!$this->indexExists('material_targets', 'idx_material_targets_group')) {
                $table->index('group_id', 'idx_material_targets_group');
            }
            // Index for subgroup_id lookups
            if (!$this->indexExists('material_targets', 'idx_material_targets_subgroup')) {
                $table->index('subgroup_id', 'idx_material_targets_subgroup');
            }
        });

        // Add index for subgroups.group_id (used in visible scope queries)
        Schema::table('subgroups', function (Blueprint $table): void {
            if (!$this->indexExists('subgroups', 'idx_subgroups_group_id')) {
                $table->index('group_id', 'idx_subgroups_group_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_items', function (Blueprint $table): void {
            $table->dropIndex('idx_schedule_items_group_conflict');
        });

        Schema::table('assignment_targets', function (Blueprint $table): void {
            $table->dropIndex('idx_assignment_targets_student');
            $table->dropIndex('idx_assignment_targets_group');
            $table->dropIndex('idx_assignment_targets_subgroup');
        });

        Schema::table('material_targets', function (Blueprint $table): void {
            $table->dropIndex('idx_material_targets_student');
            $table->dropIndex('idx_material_targets_group');
            $table->dropIndex('idx_material_targets_subgroup');
        });

        Schema::table('subgroups', function (Blueprint $table): void {
            $table->dropIndex('idx_subgroups_group_id');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
            $doctrineTable = $doctrineSchemaManager->introspectTable($table);
            
            return $doctrineTable->hasIndex($index);
        } catch (\Exception $e) {
            // If table doesn't exist or other error, assume index doesn't exist
            return false;
        }
    }
};

