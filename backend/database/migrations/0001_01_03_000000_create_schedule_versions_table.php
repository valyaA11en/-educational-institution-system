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
        Schema::create('schedule_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('term_id');
            $table->string('status')->default('draft'); // draft, published, archived
            $table->unsignedBigInteger('created_by');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->index(['term_id', 'status']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_versions');
    }
};

