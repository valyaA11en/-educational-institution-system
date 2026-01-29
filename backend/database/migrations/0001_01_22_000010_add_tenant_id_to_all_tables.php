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
            'teacher_subject_group',
            'schedule_versions', 'schedule_items', 'schedule_replacements', 'duty_shifts',
            'lessons', 'grades', 'grade_changes', 'attendance', 'grade_period_summaries',
            'materials', 'material_targets', 'material_reads',
            'assignments', 'assignment_targets', 'submissions', 'submission_files',
            'documents', 'doc_templates', 'document_routes', 'document_ack', 'document_registry',
            'notifications', 'notification_settings',
            'chat_threads', 'chat_members', 'chat_messages', 'chat_reports', 'chat_thread_settings',
            'tickets', 'ticket_messages',
            'rules', 'risks',
            'webhook_endpoints', 'webhook_deliveries',
            'settings', 'files',
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

        // Composite indexes for common queries
        $compositeIndexes = [
            'schedule_items' => [['tenant_id', 'date', 'time_slot_id']],
            'grades' => [['tenant_id', 'student_user_id'], ['tenant_id', 'lesson_id']],
            'assignments' => [['tenant_id', 'teacher_user_id']],
            'documents' => [['tenant_id', 'status']],
            'tickets' => [['tenant_id', 'status'], ['tenant_id', 'assigned_to']],
        ];

        foreach ($compositeIndexes as $tableName => $indexes) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexes, $tableName) {
                    foreach ($indexes as $indexColumns) {
                        $indexName = 'idx_' . $tableName . '_' . implode('_', $indexColumns);
                        if (!$this->indexExists($tableName, $indexName)) {
                            $table->index($indexColumns, $indexName);
                        }
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'groups', 'subgroups', 'subjects', 'rooms', 'time_slots',
            'teacher_subject_group',
            'schedule_versions', 'schedule_items', 'schedule_replacements', 'duty_shifts',
            'lessons', 'grades', 'grade_changes', 'attendance', 'grade_period_summaries',
            'materials', 'material_targets', 'material_reads',
            'assignments', 'assignment_targets', 'submissions', 'submission_files',
            'documents', 'doc_templates', 'document_routes', 'document_ack', 'document_registry',
            'notifications', 'notification_settings',
            'chat_threads', 'chat_members', 'chat_messages', 'chat_reports', 'chat_thread_settings',
            'tickets', 'ticket_messages',
            'rules', 'risks',
            'webhook_endpoints', 'webhook_deliveries',
            'settings', 'files',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'tenant_id')) {
                        $foreignKeyName = $tableName . '_tenant_id_foreign';
                        $indexName = $tableName . '_tenant_id_index';
                        
                        try {
                            $table->dropForeign([$foreignKeyName]);
                        } catch (\Exception $e) {
                            // Foreign key might not exist
                        }
                        
                        try {
                            $table->dropIndex($indexName);
                        } catch (\Exception $e) {
                            // Index might not exist
                        }
                        
                        $table->dropColumn('tenant_id');
                    }
                });
            }
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
            $doctrineTable = $doctrineSchemaManager->introspectTable($table);
            return $doctrineTable->hasIndex($index);
        } catch (\Exception $e) {
            return false;
        }
    }
};


