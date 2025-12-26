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
        // Check if table exists and has old structure
        if (!Schema::hasTable('risks')) {
            // Create new table structure
            Schema::create('risks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('term_id')->nullable();
                $table->enum('risk_type', ['avg_low', 'absences_high', 'debts_high', 'no_activity']);
                $table->enum('level', ['green', 'yellow', 'red'])->default('green');
                $table->decimal('score', 6, 2)->default(0);
                $table->jsonb('details_json')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('term_id')->references('id')->on('terms')->onDelete('set null');

                $table->unique(['user_id', 'term_id', 'risk_type']);
                $table->index(['user_id', 'term_id']);
                $table->index('level');
                $table->index('risk_type');
            });
            return;
        }

        // Drop old structure if exists
        if (Schema::hasColumn('risks', 'entity_type')) {
            Schema::table('risks', function (Blueprint $table) {
                // Drop old indexes
                try {
                    $table->dropIndex(['entity_type', 'entity_id']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
                try {
                    $table->dropIndex(['term_id', 'risk_level']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
                
                // Drop old foreign keys
                try {
                    if (Schema::hasColumn('risks', 'calculated_by')) {
                        $table->dropForeign(['calculated_by']);
                    }
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                try {
                    if (Schema::hasColumn('risks', 'resolved_by')) {
                        $table->dropForeign(['resolved_by']);
                    }
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            });

            // Drop old columns
            Schema::table('risks', function (Blueprint $table) {
                $columnsToDrop = [
                    'entity_type',
                    'entity_id',
                    'risk_level',
                    'risk_score',
                    'factors',
                    'metadata',
                    'calculated_by',
                    'resolved_at',
                    'resolution_notes',
                    'resolved_by',
                ];

                foreach ($columnsToDrop as $column) {
                    if (Schema::hasColumn('risks', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // Add new columns
        Schema::table('risks', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->after('id');
            $table->enum('risk_type', ['avg_low', 'absences_high', 'debts_high', 'no_activity'])->after('term_id');
            $table->enum('level', ['green', 'yellow', 'red'])->default('green')->after('risk_type');
            $table->decimal('score', 6, 2)->default(0)->after('level');
            $table->jsonb('details_json')->nullable()->after('score');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['user_id', 'term_id', 'risk_type']);
            
            // Indexes
            $table->index(['user_id', 'term_id']);
            $table->index('level');
            $table->index('risk_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risks', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'term_id', 'risk_type']);
            $table->dropIndex(['user_id', 'term_id']);
            $table->dropIndex(['level']);
            $table->dropIndex(['risk_type']);
            $table->dropForeign(['user_id']);
            
            $table->dropColumn([
                'user_id',
                'risk_type',
                'level',
                'score',
                'details_json',
            ]);
        });

        // Restore old structure (simplified)
        Schema::table('risks', function (Blueprint $table) {
            $table->string('entity_type')->after('id');
            $table->unsignedBigInteger('entity_id')->after('entity_type');
            $table->string('risk_level')->after('entity_id');
            $table->decimal('risk_score', 5, 2)->default(0)->after('risk_level');
            $table->jsonb('factors')->after('risk_score');
            $table->jsonb('metadata')->nullable()->after('factors');
            $table->unsignedBigInteger('calculated_by')->nullable()->after('calculated_at');
            $table->timestamp('resolved_at')->nullable()->after('calculated_by');
            $table->text('resolution_notes')->nullable()->after('resolved_at');
            $table->unsignedBigInteger('resolved_by')->nullable()->after('resolution_notes');
            
            $table->foreign('calculated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['entity_type', 'entity_id']);
            $table->index(['term_id', 'risk_level']);
        });
    }
};

