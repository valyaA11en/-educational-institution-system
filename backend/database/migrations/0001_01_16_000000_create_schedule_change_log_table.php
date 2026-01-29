<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_change_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version_id');
            $table->string('action'); // create, update, delete, force_override, publish
            $table->unsignedBigInteger('schedule_item_id')->nullable();
            $table->unsignedBigInteger('actor_user_id');
            $table->jsonb('before_json')->nullable();
            $table->jsonb('after_json')->nullable();
            $table->timestamps();

            $table->foreign('version_id')->references('id')->on('schedule_versions')->onDelete('cascade');
            $table->foreign('schedule_item_id')->references('id')->on('schedule_items')->onDelete('set null');
            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('version_id');
            $table->index('schedule_item_id');
            $table->index('actor_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_change_log');
    }
};








