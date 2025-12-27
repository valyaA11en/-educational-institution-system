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
        Schema::create('user_links_parent_child', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parent_user_id');
            $table->unsignedBigInteger('student_user_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('link_code_hash');
            $table->timestamps();

            $table->foreign('parent_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['parent_user_id', 'student_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_links_parent_child');
    }
};









