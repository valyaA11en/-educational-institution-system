<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->enum('role', ['chair', 'member']);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['exam_id', 'user_id']);
            $table->index('exam_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_commissions');
    }
};

