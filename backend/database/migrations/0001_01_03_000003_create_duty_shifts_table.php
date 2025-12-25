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
        Schema::create('duty_shifts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');
            $table->unsignedBigInteger('time_slot_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('teacher_user_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('restrict');
            $table->foreign('teacher_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['date', 'time_slot_id']);
            $table->index(['teacher_user_id', 'date']);
            $table->index(['room_id', 'date']);
            $table->index(['group_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duty_shifts');
    }
};

