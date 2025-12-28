<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_user_id');
            $table->string('type'); // achievement, project, certificate, contest, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->string('file_path')->nullable(); // Ссылка на файл в MinIO
            $table->jsonb('metadata_json')->nullable(); // Дополнительные данные
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['tenant_id', 'student_user_id', 'type']);
            $table->index(['tenant_id', 'student_user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
    }
};

