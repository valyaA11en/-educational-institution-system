<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('signed_by')->nullable()->after('created_by');
            $table->timestamp('signed_at')->nullable()->after('signed_by');

            $table->foreign('signed_by')->references('id')->on('users')->onDelete('set null');
            $table->index('signed_by');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['signed_by']);
            $table->dropIndex(['signed_by']);
            $table->dropColumn(['signed_by', 'signed_at']);
        });
    }
};








