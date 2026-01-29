<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_portfolio_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_user_id');
            $table->string('type'); // assignment|contest|certificate|achievement
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('related_entity_type')->nullable(); // Grade, ContestResult, Document, etc.
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('files')->onDelete('set null');

            $table->index(['tenant_id', 'student_user_id']);
            $table->index(['tenant_id', 'student_user_id', 'type']);
            $table->index(['related_entity_type', 'related_entity_id']);
            $table->index(['is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_portfolio_items');
    }
};


