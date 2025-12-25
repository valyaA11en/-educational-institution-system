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
        Schema::create('schedule_replacements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('schedule_item_id');
            $table->date('date');
            $table->unsignedBigInteger('new_teacher_user_id')->nullable();
            $table->unsignedBigInteger('new_room_id')->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('status')->default('draft'); // draft, approved, rejected, applied
            $table->timestamps();

            $table->foreign('schedule_item_id')->references('id')->on('schedule_items')->onDelete('cascade');
            $table->foreign('new_teacher_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('new_room_id')->references('id')->on('rooms')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['date', 'status']);
            $table->index('schedule_item_id');
            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_replacements');
    }
};

