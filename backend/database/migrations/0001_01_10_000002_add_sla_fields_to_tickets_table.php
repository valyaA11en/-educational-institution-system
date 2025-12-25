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
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('sla_level')->nullable()->after('priority'); // bronze, silver, gold, platinum
            $table->integer('response_time_minutes')->nullable()->after('sla_level'); // Время ответа в минутах
            $table->integer('resolution_time_minutes')->nullable()->after('response_time_minutes'); // Время решения в минутах
            $table->timestamp('sla_response_due_at')->nullable()->after('sla_due_at'); // Дедлайн для первого ответа
            $table->timestamp('sla_first_response_at')->nullable()->after('sla_response_due_at'); // Время первого ответа
            $table->jsonb('escalation_rules')->nullable()->after('sla_reminder_sent_at'); // Правила эскалации
            
            $table->index('sla_level');
            $table->index('sla_response_due_at');
            $table->index(['status', 'sla_response_due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'sla_level',
                'response_time_minutes',
                'resolution_time_minutes',
                'sla_response_due_at',
                'sla_first_response_at',
                'escalation_rules',
            ]);
        });
    }
};

