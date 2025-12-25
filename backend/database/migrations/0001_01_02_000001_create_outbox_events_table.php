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
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_type');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('payload_json');
            $table->string('idempotency_key')->unique();
            $table->string('status')->default('new'); // new, processing, sent, failed
            $table->integer('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('actor_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
