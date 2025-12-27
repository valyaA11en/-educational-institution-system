<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('student_user_id');
            $table->decimal('score', 6, 2)->nullable();
            $table->smallInteger('grade_value')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['exam_id', 'student_user_id']);
            $table->index('exam_id');
            $table->index('student_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};







