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
        Schema::table('audit_log', function (Blueprint $table) {
            // Index on action
            $table->index('action', 'audit_log_action_index');

            // Index on entity
            $table->index('entity', 'audit_log_entity_index');

            // Composite index on tenant_id, action, entity, created_at for efficient filtering
            $table->index(['tenant_id', 'action', 'entity', 'created_at'], 'audit_log_tenant_action_entity_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropIndex('audit_log_action_index');
            $table->dropIndex('audit_log_entity_index');
            $table->dropIndex('audit_log_tenant_action_entity_created_index');
        });
    }
};

