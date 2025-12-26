<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('term_id');
            $table->jsonb('config_json'); // пороги допусков: max_debts, max_absences, min_avg
            $table->timestamps();

            $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
            $table->unique('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_rules');
    }
};

