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
        Schema::create('assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('teacher_user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->smallInteger('max_attempts')->default(1);
            $table->unsignedBigInteger('max_file_size')->nullable();
            $table->jsonb('allowed_types')->nullable();
            $table->string('visibility_scope'); // group, subgroup, individual
            $table->timestamps();

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('restrict');
            $table->foreign('teacher_user_id')->references('id')->on('users')->onDelete('restrict');

            $table->index('due_at');
            $table->index('subject_id');
            $table->index('teacher_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};

