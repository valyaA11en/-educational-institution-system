<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update curriculum_plans table
        Schema::table('curriculum_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('curriculum_plans', 'status')) {
                $table->string('status')->default('draft')->after('template_id'); // draft, active, archived
            }
            if (!Schema::hasColumn('curriculum_plans', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('teacher_user_id');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Update curriculum_topics table
        Schema::table('curriculum_topics', function (Blueprint $table) {
            // Add order_no if it doesn't exist (keep order for backward compatibility)
            if (!Schema::hasColumn('curriculum_topics', 'order_no')) {
                $table->smallInteger('order_no')->nullable()->after('order');
            }
            if (!Schema::hasColumn('curriculum_topics', 'hours')) {
                $table->integer('hours')->nullable()->after('order_no'); // Alias for hours_total
            }
            if (!Schema::hasColumn('curriculum_topics', 'planned_date_from')) {
                $table->date('planned_date_from')->nullable()->after('control_type');
            }
            if (!Schema::hasColumn('curriculum_topics', 'planned_date_to')) {
                $table->date('planned_date_to')->nullable()->after('planned_date_from');
            }
        });

        // Copy data from order to order_no and hours_total to hours
        if (Schema::hasColumn('curriculum_topics', 'order') && Schema::hasColumn('curriculum_topics', 'order_no')) {
            DB::statement('UPDATE curriculum_topics SET order_no = "order" WHERE order_no IS NULL');
        }
        if (Schema::hasColumn('curriculum_topics', 'hours_total') && Schema::hasColumn('curriculum_topics', 'hours')) {
            DB::statement('UPDATE curriculum_topics SET hours = hours_total WHERE hours IS NULL');
        }

        // Create ktp_topic_links table (unified links table)
        if (!Schema::hasTable('ktp_topic_links')) {
            Schema::create('ktp_topic_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ktp_topic_id');
                $table->unsignedBigInteger('lesson_id')->nullable();
                $table->unsignedBigInteger('assignment_id')->nullable();
                $table->unsignedBigInteger('material_id')->nullable();
                $table->timestamps();

                $table->foreign('ktp_topic_id')->references('id')->on('curriculum_topics')->onDelete('cascade');
                $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
                $table->foreign('assignment_id')->references('id')->on('assignments')->onDelete('cascade');
                $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');

                $table->index('ktp_topic_id');
                // Note: Unique constraints with nullable columns in PostgreSQL require partial indexes
                // For now, we'll rely on application logic to ensure only one link type per topic
            });
        }

        // Create ktp_templates table
        if (!Schema::hasTable('ktp_templates')) {
            Schema::create('ktp_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('name');
                $table->jsonb('data_json')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

                $table->index('subject_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ktp_templates');
        Schema::dropIfExists('ktp_topic_links');

        Schema::table('curriculum_topics', function (Blueprint $table) {
            if (Schema::hasColumn('curriculum_topics', 'order_no')) {
                $table->dropColumn('order_no');
            }
            if (Schema::hasColumn('curriculum_topics', 'hours')) {
                $table->dropColumn('hours');
            }
            if (Schema::hasColumn('curriculum_topics', 'planned_date_from')) {
                $table->dropColumn('planned_date_from');
            }
            if (Schema::hasColumn('curriculum_topics', 'planned_date_to')) {
                $table->dropColumn('planned_date_to');
            }
        });

        Schema::table('curriculum_plans', function (Blueprint $table) {
            if (Schema::hasColumn('curriculum_plans', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('curriculum_plans', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
