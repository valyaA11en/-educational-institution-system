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
            $table->integer('sla_hours')->default(72)->after('priority');
            $table->timestamp('first_response_due_at')->nullable()->after('sla_hours');
            $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            $table->timestamp('first_response_at')->nullable()->after('resolution_due_at');
            $table->timestamp('resolved_at')->nullable()->after('first_response_at');
            $table->boolean('is_overdue')->default(false)->after('resolved_at');

            $table->index(['is_overdue', 'status']);
            $table->index('resolution_due_at');
            $table->index('first_response_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['is_overdue', 'status']);
            $table->dropIndex(['resolution_due_at']);
            $table->dropIndex(['first_response_due_at']);
            
            $table->dropColumn([
                'sla_hours',
                'first_response_due_at',
                'resolution_due_at',
                'first_response_at',
                'resolved_at',
                'is_overdue',
            ]);
        });
    }
};
