<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contest_id');
            $table->unsignedBigInteger('submission_id');
            $table->smallInteger('place')->nullable();
            $table->decimal('final_score', 10, 2);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('contest_id')->references('id')->on('contests')->onDelete('cascade');
            $table->foreign('submission_id')->references('id')->on('contest_submissions')->onDelete('cascade');
            $table->unique(['contest_id', 'submission_id']);
            $table->index('contest_id');
            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_results');
    }
};

