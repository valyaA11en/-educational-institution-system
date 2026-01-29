<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_timeline', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_user_id');
            $table->string('event_type'); // grade.created, assignment.submitted, lesson.attended, etc.
            $table->string('entity_type')->nullable(); // Grade, Assignment, Lesson, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('metadata_json')->nullable(); // Дополнительные данные
            $table->date('event_date');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['tenant_id', 'student_user_id', 'event_date']);
            $table->index(['tenant_id', 'event_type', 'event_date']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_timeline');
    }
};


