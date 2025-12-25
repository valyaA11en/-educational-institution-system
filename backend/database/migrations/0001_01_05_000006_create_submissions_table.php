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
        Schema::create('submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('student_user_id');
            $table->string('status')->default('draft'); // draft, submitted, returned, graded, late
            $table->timestamp('submitted_at')->nullable();
            $table->text('text')->nullable();
            $table->timestamps();

            $table->foreign('assignment_id')->references('id')->on('assignments')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('assignment_id');
            $table->index('student_user_id');
            $table->index(['assignment_id', 'student_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};

