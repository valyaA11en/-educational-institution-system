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
        Schema::create('schedule_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('version_id');
            $table->date('date');
            $table->unsignedBigInteger('time_slot_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('subgroup_id')->nullable();
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('teacher_user_id');
            $table->unsignedBigInteger('room_id');
            $table->text('override_reason')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('version_id')->references('id')->on('schedule_versions')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('restrict');
            $table->foreign('subgroup_id')->references('id')->on('subgroups')->onDelete('restrict');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('restrict');
            $table->foreign('teacher_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['date', 'time_slot_id']);
            $table->index(['teacher_user_id', 'date']);
            $table->index(['room_id', 'date']);
            $table->index(['group_id', 'date']);
            $table->index('version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_items');
    }
};


