<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_topics_analysis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_user_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('ktp_topic_id')->nullable(); // Тема из КТП
            $table->string('topic_name');
            $table->integer('failed_attempts')->default(0); // Количество неудачных попыток
            $table->decimal('average_grade', 3, 2)->nullable(); // Средняя оценка по теме
            $table->date('last_attempt_date')->nullable();
            $table->jsonb('details_json')->nullable(); // Детали: какие задания, оценки и т.д.
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');

            $table->index(['tenant_id', 'student_user_id', 'subject_id']);
            $table->index(['tenant_id', 'subject_id', 'failed_attempts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_topics_analysis');
    }
};

