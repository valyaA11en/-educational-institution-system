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
        Schema::create('grade_period_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_user_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('term_id');
            $table->decimal('avg_value', 5, 2);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');
            // TODO: добавить внешние ключи на subjects и terms, когда таблицы будут созданы

            $table->unique(['student_user_id', 'subject_id', 'term_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_period_summaries');
    }
};



