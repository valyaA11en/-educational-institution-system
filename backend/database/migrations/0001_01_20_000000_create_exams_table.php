<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('term_id');
            $table->enum('type', ['exam', 'test', 'attestation']);
            $table->string('title');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->timestamp('date_at');
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('term_id');
            $table->index('group_id');
            $table->index('date_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};

