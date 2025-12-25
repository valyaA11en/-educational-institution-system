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
        Schema::create('document_registry', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type');
            $table->smallInteger('year');
            $table->integer('last_number')->default(0);
            $table->timestamp('updated_at');

            $table->unique(['type', 'year']);
            $table->index(['type', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_registry');
    }
};

