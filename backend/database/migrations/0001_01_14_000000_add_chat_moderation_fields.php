<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table) {
            $table->boolean('is_announcement')->default(false)->after('type');
            $table->time('quiet_hours_start')->nullable()->after('is_announcement');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
            $table->integer('max_attachment_size')->nullable()->after('quiet_hours_end');
            $table->json('allowed_attachment_types')->nullable()->after('max_attachment_size');
        });

        Schema::create('chat_complaints', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('reported_by');
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, reviewed, resolved, dismissed
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('chat_threads')->onDelete('cascade');
            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->foreign('reported_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['thread_id', 'status']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_complaints');
        
        Schema::table('chat_threads', function (Blueprint $table) {
            $table->dropColumn([
                'is_announcement',
                'quiet_hours_start',
                'quiet_hours_end',
                'max_attachment_size',
                'allowed_attachment_types',
            ]);
        });
    }
};


