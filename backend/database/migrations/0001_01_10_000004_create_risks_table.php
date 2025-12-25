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
        Schema::create('risks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('entity_type'); // student, group, subject, etc.
            $table->unsignedBigInteger('entity_id');
            $table->string('risk_level'); // none, low, medium, high, critical
            $table->decimal('risk_score', 5, 2)->default(0); // 0-100
            $table->jsonb('factors'); // Массив факторов риска
            $table->jsonb('metadata')->nullable(); // Дополнительные данные
            $table->unsignedBigInteger('term_id')->nullable(); // Связь с учебным периодом
            $table->unsignedBigInteger('calculated_by')->nullable(); // Кто рассчитал
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('resolved_at')->nullable(); // Когда риск был устранен
            $table->text('resolution_notes')->nullable(); // Примечания по устранению
            $table->unsignedBigInteger('resolved_by')->nullable(); // Кто устранил риск
            $table->timestamps();

            $table->foreign('term_id')->references('id')->on('terms')->onDelete('set null');
            $table->foreign('calculated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['entity_type', 'entity_id']);
            $table->index('risk_level');
            $table->index('risk_score');
            $table->index(['term_id', 'risk_level']);
            $table->index('calculated_at');
            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};

