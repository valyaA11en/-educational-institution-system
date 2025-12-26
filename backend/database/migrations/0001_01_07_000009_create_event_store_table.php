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
        Schema::create('event_store', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('channel_key');
            $table->string('event_type');
            $table->jsonb('payload_json');
            $table->timestamp('created_at');

            $table->index(['channel_key', 'created_at']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_store');
    }
};


