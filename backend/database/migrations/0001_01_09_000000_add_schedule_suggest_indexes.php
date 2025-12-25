<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_items', function (Blueprint $table): void {
            // Index for room conflict checks
            $table->index(['version_id', 'date', 'time_slot_id', 'room_id'], 'idx_schedule_items_room_conflict');
            
            // Index for teacher conflict checks
            $table->index(['version_id', 'date', 'time_slot_id', 'teacher_user_id'], 'idx_schedule_items_teacher_conflict');
        });

        Schema::table('teacher_subject_group', function (Blueprint $table): void {
            // Index for teacher suggestions
            $table->index(['subject_id', 'group_id', 'subgroup_id'], 'idx_teacher_subject_group_lookup');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            // Index for capacity filtering and sorting
            $table->index('capacity', 'idx_rooms_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_items', function (Blueprint $table): void {
            $table->dropIndex('idx_schedule_items_room_conflict');
            $table->dropIndex('idx_schedule_items_teacher_conflict');
        });

        Schema::table('teacher_subject_group', function (Blueprint $table): void {
            $table->dropIndex('idx_teacher_subject_group_lookup');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropIndex('idx_rooms_capacity');
        });
    }
};

