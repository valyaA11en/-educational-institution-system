<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_rubrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contest_id');
            $table->string('title');
            $table->jsonb('criteria_json'); // [{key,title,maxScore,weight?}]
            $table->timestamps();

            $table->foreign('contest_id')->references('id')->on('contests')->onDelete('cascade');
            $table->index('contest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_rubrics');
    }
};

