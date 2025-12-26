<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_submission_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('file_id');
            $table->timestamps();

            $table->foreign('submission_id')->references('id')->on('contest_submissions')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
            $table->unique(['submission_id', 'file_id']);
            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_submission_files');
    }
};

