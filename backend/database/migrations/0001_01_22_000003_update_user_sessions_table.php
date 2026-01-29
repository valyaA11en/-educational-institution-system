<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            // Переименование существующих полей
            if (Schema::hasColumn('user_sessions', 'ip') && !Schema::hasColumn('user_sessions', 'ip_address')) {
                $table->renameColumn('ip', 'ip_address');
            }
            if (Schema::hasColumn('user_sessions', 'last_seen_at') && !Schema::hasColumn('user_sessions', 'last_activity')) {
                $table->renameColumn('last_seen_at', 'last_activity');
            }
            
            // Добавление новых полей
            if (!Schema::hasColumn('user_sessions', 'device_id')) {
                $table->string('device_id')->unique()->after('user_id');
            }
            if (!Schema::hasColumn('user_sessions', 'device_name')) {
                $table->string('device_name')->nullable()->after('device_id');
            }
            if (!Schema::hasColumn('user_sessions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('last_activity');
            }
            if (!Schema::hasColumn('user_sessions', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
        
        // Добавление индексов
        Schema::table('user_sessions', function (Blueprint $table) {
            if (!$this->indexExists('user_sessions', 'user_sessions_user_id_is_active_index')) {
                $table->index(['user_id', 'is_active']);
            }
            if (!$this->indexExists('user_sessions', 'user_sessions_device_id_index')) {
                $table->index('device_id');
            }
        });
    }
    
    private function indexExists($table, $indexName)
    {
        $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = '$table' AND indexname = '$indexName'");
        return count($indexes) > 0;
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            // Удаление добавленных полей
            if (Schema::hasColumn('user_sessions', 'device_id')) {
                $table->dropIndex(['device_id']);
                $table->dropColumn('device_id');
            }
            if (Schema::hasColumn('user_sessions', 'device_name')) {
                $table->dropColumn('device_name');
            }
            if (Schema::hasColumn('user_sessions', 'is_active')) {
                $table->dropIndex(['user_id', 'is_active']);
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('user_sessions', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
            
            // Восстановление исходных имен полей
            if (Schema::hasColumn('user_sessions', 'ip_address')) {
                $table->renameColumn('ip_address', 'ip');
            }
            if (Schema::hasColumn('user_sessions', 'last_activity')) {
                $table->renameColumn('last_activity', 'last_seen_at');
            }
        });
    }
};


