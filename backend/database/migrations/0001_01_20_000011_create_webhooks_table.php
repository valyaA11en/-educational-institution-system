<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('method')->default('POST');
            $table->json('events'); // массив событий для подписки
            $table->json('headers')->nullable(); // кастомные заголовки
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};

