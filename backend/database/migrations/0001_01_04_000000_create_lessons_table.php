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
        Schema::create('lessons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('schedule_item_id')->nullable();
            $table->date('date');
            $table->string('topic')->nullable();
            $table->unsignedBigInteger('ktp_topic_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('schedule_item_id')->references('id')->on('schedule_items')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['date']);
            $table->index(['schedule_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};



