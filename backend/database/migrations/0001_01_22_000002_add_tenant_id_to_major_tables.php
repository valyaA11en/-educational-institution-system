<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'groups', 'subgroups', 'subjects', 'rooms', 'time_slots',
            'schedule_versions', 'schedule_items', 'schedule_replacements',
            'assignments', 'materials', 'grades', 'lessons',
            'documents', 'doc_templates', 'chat_threads', 'tickets',
            'exams', 'contests', 'curriculum_plans', 'webhook_endpoints',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($table) {
                    if (!Schema::hasColumn($table, 'tenant_id')) {
                        $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                        $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                        $table->index('tenant_id');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'groups', 'subgroups', 'subjects', 'rooms', 'time_slots',
            'schedule_versions', 'schedule_items', 'schedule_replacements',
            'assignments', 'materials', 'grades', 'lessons',
            'documents', 'doc_templates', 'chat_threads', 'tickets',
            'exams', 'contests', 'curriculum_plans', 'webhook_endpoints',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($table) {
                    if (Schema::hasColumn($table, 'tenant_id')) {
                        $table->dropForeign([$table . '_tenant_id_foreign']);
                        $table->dropIndex([$table . '_tenant_id_index']);
                        $table->dropColumn('tenant_id');
                    }
                });
            }
        }
    }
};


