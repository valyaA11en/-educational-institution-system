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
        Schema::create('groups', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('subgroups', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('group_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->jsonb('attributes')->nullable();
            $table->timestamps();
        });

        Schema::create('time_slots', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_slots');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('subgroups');
        Schema::dropIfExists('groups');
    }
};


