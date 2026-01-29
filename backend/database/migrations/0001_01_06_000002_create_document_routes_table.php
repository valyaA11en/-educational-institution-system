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
        Schema::create('document_routes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id');
            $table->smallInteger('step_no');
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamp('decided_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('approver_role_id')->references('id')->on('roles')->onDelete('restrict');
            $table->foreign('approver_user_id')->references('id')->on('users')->onDelete('restrict');

            $table->index(['document_id', 'step_no']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_routes');
    }
};








