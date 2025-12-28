<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('student_timeline');
        
        Schema::create('student_timeline_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_user_id');
            $table->string('event_type'); // enrollment.created, attendance.marked, grade.created, etc.
            $table->date('event_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('related_entity_type')->nullable(); // Grade, Attendance, Assignment, etc.
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->jsonb('payload_json')->nullable(); // Дополнительные данные
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['tenant_id', 'student_user_id', 'event_date']);
            $table->index(['tenant_id', 'student_user_id', 'event_type']);
            $table->index(['tenant_id', 'event_type', 'event_date']);
            $table->index(['related_entity_type', 'related_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_timeline_events');
    }
};

