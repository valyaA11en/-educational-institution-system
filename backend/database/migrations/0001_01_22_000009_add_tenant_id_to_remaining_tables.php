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

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'tenant_id')) {
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

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'tenant_id')) {
                        $table->dropForeign(['tenant_id']);
                        $table->dropIndex(['tenant_id']);
                        $table->dropColumn('tenant_id');
                    }
                });
            }
        }
    }
};


