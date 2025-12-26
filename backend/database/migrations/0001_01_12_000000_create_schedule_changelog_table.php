<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_changelog', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('version_id');
            $table->unsignedBigInteger('schedule_item_id')->nullable();
            $table->string('action'); // created, updated, deleted
            $table->jsonb('before_json')->nullable();
            $table->jsonb('after_json')->nullable();
            $table->unsignedBigInteger('changed_by');
            $table->timestamps();

            $table->foreign('version_id')->references('id')->on('schedule_versions')->onDelete('cascade');
            $table->foreign('schedule_item_id')->references('id')->on('schedule_items')->onDelete('set null');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['version_id', 'created_at']);
            $table->index('schedule_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_changelog');
    }
};


