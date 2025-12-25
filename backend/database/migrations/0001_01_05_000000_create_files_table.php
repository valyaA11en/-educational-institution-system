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
        Schema::create('files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('storage_key');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->string('mime');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('restrict');
            $table->index('storage_key');
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

