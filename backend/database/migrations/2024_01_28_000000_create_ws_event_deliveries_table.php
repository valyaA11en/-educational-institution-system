<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_event_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('outbox_event_id')->nullable();
            $table->string('status')->default('sent'); // sent, acked, failed
            $table->timestamp('sent_at');
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('outbox_event_id')->references('id')->on('outbox_events')->onDelete('set null');

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'sent_at']);
            $table->index(['user_id', 'status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ws_event_deliveries');
    }
};


