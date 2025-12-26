<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contest_id');
            $table->unsignedBigInteger('participant_user_id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('contest_id')->references('id')->on('contests')->onDelete('cascade');
            $table->foreign('participant_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('contest_id');
            $table->index('participant_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_submissions');
    }
};

