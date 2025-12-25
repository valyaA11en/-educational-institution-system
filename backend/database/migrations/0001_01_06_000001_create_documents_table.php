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
        Schema::create('documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type');
            $table->string('number');
            $table->date('date');
            $table->string('status')->default('draft'); // draft, on_review, approved, signed, archived
            $table->unsignedBigInteger('template_id');
            $table->jsonb('data_json');
            $table->unsignedBigInteger('created_by');
            $table->string('verify_hash')->unique();
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('doc_templates')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['type', 'date']);
            $table->index('status');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

