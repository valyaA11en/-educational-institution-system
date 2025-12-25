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
        Schema::create('teacher_subject_group', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('teacher_user_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('subgroup_id')->nullable();
            $table->timestamps();

            $table->foreign('teacher_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('subgroup_id')->references('id')->on('subgroups')->onDelete('cascade');

            $table->unique(['teacher_user_id', 'subject_id', 'group_id', 'subgroup_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_group');
    }
};

