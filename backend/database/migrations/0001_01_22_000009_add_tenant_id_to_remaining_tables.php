<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'grades', 'lessons', 'submissions', 'submission_files',
            'chat_messages', 'chat_members', 'ticket_messages',
            'notifications', 'files', 'exam_commissions', 'exam_registrations',
            'exam_results', 'contest_submissions', 'contest_scores',
            'curriculum_topics', 'ktp_topic_links',
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
            'grades', 'lessons', 'submissions', 'submission_files',
            'chat_messages', 'chat_members', 'ticket_messages',
            'notifications', 'files', 'exam_commissions', 'exam_registrations',
            'exam_results', 'contest_submissions', 'contest_scores',
            'curriculum_topics', 'ktp_topic_links',
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


