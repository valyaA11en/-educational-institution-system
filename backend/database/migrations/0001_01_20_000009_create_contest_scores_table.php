<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contest_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('jury_user_id');
            $table->jsonb('rubric_json'); // {criterionKey: score}
            $table->decimal('total_score', 10, 2);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('contest_id')->references('id')->on('contests')->onDelete('cascade');
            $table->foreign('submission_id')->references('id')->on('contest_submissions')->onDelete('cascade');
            $table->foreign('jury_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['submission_id', 'jury_user_id']);
            $table->index('contest_id');
            $table->index('submission_id');
            $table->index('jury_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_scores');
    }
};

