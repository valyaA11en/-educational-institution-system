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
        Schema::create('ticket_slas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('sla_level'); // bronze, silver, gold, platinum
            $table->string('category')->nullable(); // null = applies to all categories
            $table->string('priority'); // low, normal, high, critical
            $table->integer('response_time_minutes'); // Время ответа в минутах
            $table->integer('resolution_time_minutes'); // Время решения в минутах
            $table->jsonb('escalation_rules')->nullable(); // Правила эскалации по уровням
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['category', 'priority', 'enabled']);
            $table->index('sla_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_slas');
    }
};

