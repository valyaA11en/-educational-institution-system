<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            if (!Schema::hasColumn('lessons', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }

            if (!Schema::hasColumn('lessons', 'status')) {
                $table->string('status')->default('planned')->after('date');
            }

            if (!Schema::hasColumn('lessons', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('ktp_topic_id');
            }

            if (!Schema::hasColumn('lessons', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }

            // Изменить topic на text если нужно
            if (Schema::hasColumn('lessons', 'topic')) {
                $table->text('topic')->nullable()->change();
            }
        });

        // Добавить уникальный индекс
        try {
            Schema::table('lessons', function (Blueprint $table) {
                $table->unique(['tenant_id', 'schedule_item_id', 'date'], 'lessons_tenant_schedule_date_unique');
            });
        } catch (\Exception $e) {
            // Индекс уже существует
        }
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropUnique('lessons_tenant_schedule_date_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'status', 'started_at', 'finished_at']);
        });
    }
};

