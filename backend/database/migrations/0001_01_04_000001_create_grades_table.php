<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->unsignedBigInteger('student_user_id');
            $table->smallInteger('value');
            $table->smallInteger('weight')->default(1);
            $table->string('grade_type')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('lesson_id')->references('id')->on('lessons')->nullOnDelete();
            // TODO: добавить внешние ключи на assignments, когда таблица будет создана
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['student_user_id']);
            $table->index(['lesson_id']);
            $table->index(['assignment_id']);
        });

        // CHECK: либо lesson_id, либо assignment_id (не оба null)
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_lesson_or_assignment CHECK ((lesson_id IS NOT NULL OR assignment_id IS NOT NULL));');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};









