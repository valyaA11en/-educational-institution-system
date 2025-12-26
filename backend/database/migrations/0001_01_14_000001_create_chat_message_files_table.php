<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('file_id');
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
            $table->unique(['message_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_files');
    }
};


