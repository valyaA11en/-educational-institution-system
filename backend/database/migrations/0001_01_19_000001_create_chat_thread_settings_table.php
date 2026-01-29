<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_thread_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('thread_id')->unique();
            $table->string('mode')->default('standard'); // standard, announcements
            $table->jsonb('quiet_hours')->nullable(); // e.g., [{"start": "22:00", "end": "08:00"}]
            $table->boolean('attachments_enabled')->default(true);
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('chat_threads')->onDelete('cascade');

            $table->index('thread_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_thread_settings');
    }
};








