<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('teacher_user_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_template')->default(false);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->timestamps();

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('restrict');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('restrict');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('restrict');
            $table->foreign('teacher_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('template_id')->references('id')->on('curriculum_plans')->onDelete('set null');

            $table->index(['subject_id', 'group_id', 'term_id']);
        });

        Schema::create('curriculum_topics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('curriculum_plan_id');
            $table->smallInteger('order');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('hours_total');
            $table->integer('hours_lecture')->default(0);
            $table->integer('hours_practice')->default(0);
            $table->integer('hours_lab')->default(0);
            $table->string('control_type')->nullable(); // exam, test, project, etc
            $table->timestamps();

            $table->foreign('curriculum_plan_id')->references('id')->on('curriculum_plans')->onDelete('cascade');
            $table->index(['curriculum_plan_id', 'order']);
        });

        Schema::create('curriculum_topic_lessons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('topic_id');
            $table->unsignedBigInteger('lesson_id');
            $table->timestamps();

            $table->foreign('topic_id')->references('id')->on('curriculum_topics')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
            $table->unique(['topic_id', 'lesson_id']);
        });

        Schema::create('curriculum_topic_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('topic_id');
            $table->unsignedBigInteger('assignment_id');
            $table->timestamps();

            $table->foreign('topic_id')->references('id')->on('curriculum_topics')->onDelete('cascade');
            $table->foreign('assignment_id')->references('id')->on('assignments')->onDelete('cascade');
            $table->unique(['topic_id', 'assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_topic_assignments');
        Schema::dropIfExists('curriculum_topic_lessons');
        Schema::dropIfExists('curriculum_topics');
        Schema::dropIfExists('curriculum_plans');
    }
};


