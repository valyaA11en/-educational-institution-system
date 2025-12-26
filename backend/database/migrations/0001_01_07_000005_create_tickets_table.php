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
        Schema::create('tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('created_by');
            $table->string('category');
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('open'); // open, in_progress, resolved, closed
            $table->string('priority')->default('normal'); // low, normal, high
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'priority']);
            $table->index('assigned_to');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};


