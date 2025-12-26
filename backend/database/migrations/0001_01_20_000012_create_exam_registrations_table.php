<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('student_user_id');
            $table->enum('status', ['registered', 'admitted', 'not_admitted', 'passed', 'failed'])->default('registered');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['exam_id', 'student_user_id']);
            $table->index('exam_id');
            $table->index('student_user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_registrations');
    }
};

