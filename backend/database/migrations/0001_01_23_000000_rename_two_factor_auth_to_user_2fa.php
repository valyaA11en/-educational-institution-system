<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if old table exists
        if (Schema::hasTable('two_factor_auth')) {
            // Rename table
            Schema::rename('two_factor_auth', 'user_2fa');
        }

        // Update columns if table exists
        if (Schema::hasTable('user_2fa')) {
            Schema::table('user_2fa', function (Blueprint $table) {
                if (Schema::hasColumn('user_2fa', 'secret')) {
                    $table->renameColumn('secret', 'totp_secret');
                }
                if (Schema::hasColumn('user_2fa', 'recovery_codes')) {
                    $table->renameColumn('recovery_codes', 'recovery_codes_json');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_2fa')) {
            Schema::table('user_2fa', function (Blueprint $table) {
                if (Schema::hasColumn('user_2fa', 'totp_secret')) {
                    $table->renameColumn('totp_secret', 'secret');
                }
                if (Schema::hasColumn('user_2fa', 'recovery_codes_json')) {
                    $table->renameColumn('recovery_codes_json', 'recovery_codes');
                }
            });

            Schema::rename('user_2fa', 'two_factor_auth');
        }
    }
};

