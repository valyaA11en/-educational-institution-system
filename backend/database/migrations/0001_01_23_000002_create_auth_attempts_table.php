<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identifier'); // email or IP
            $table->string('type')->default('email'); // email or ip
            $table->integer('attempts')->default(1);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_attempt_at');
            $table->timestamps();

            $table->index(['identifier', 'type']);
            $table->index('locked_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_attempts');
    }
};


