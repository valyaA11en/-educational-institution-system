<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('priority')->default('medium')->change(); // low, medium, high, critical
            $table->timestamp('sla_due_at')->nullable()->after('due_at');
            $table->timestamp('sla_resolved_at')->nullable()->after('sla_due_at');
            $table->timestamp('sla_reminder_sent_at')->nullable()->after('sla_resolved_at');
            $table->timestamp('closed_at')->nullable()->after('sla_reminder_sent_at');
            
            $table->index('sla_due_at');
            $table->index(['status', 'sla_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_due_at', 'sla_resolved_at', 'sla_reminder_sent_at', 'closed_at']);
            $table->string('priority')->default('normal')->change();
        });
    }
};








