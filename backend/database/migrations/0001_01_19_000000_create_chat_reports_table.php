<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('reported_by');
            $table->text('reason');
            $table->string('status')->default('open'); // open, reviewed, closed
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('chat_threads')->onDelete('cascade');
            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->foreign('reported_by')->references('id')->on('users')->onDelete('cascade');

            $table->index('thread_id');
            $table->index('message_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reports');
    }
};








