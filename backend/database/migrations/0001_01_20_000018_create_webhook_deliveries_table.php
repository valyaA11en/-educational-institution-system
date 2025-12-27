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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('webhook_endpoint_id');
            $table->unsignedBigInteger('outbox_event_id');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('webhook_endpoint_id')->references('id')->on('webhook_endpoints')->onDelete('cascade');
            $table->foreign('outbox_event_id')->references('id')->on('outbox_events')->onDelete('cascade');

            $table->index(['status', 'created_at']);
            $table->index('webhook_endpoint_id');
            $table->index('outbox_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};

