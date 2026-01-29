<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_performance_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('ktp_topic_id');
            $table->unsignedBigInteger('term_id')->nullable();
            $table->integer('students_total')->default(0);
            $table->integer('students_failed')->default(0); // avg <= 2
            $table->decimal('fail_percent', 5, 2)->default(0.00); // процент неуспевающих
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('ktp_topic_id')->references('id')->on('curriculum_topics')->onDelete('cascade');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('set null');

            $table->index(['tenant_id', 'subject_id', 'ktp_topic_id', 'term_id']);
            $table->index(['tenant_id', 'fail_percent']);
            $table->index(['calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_performance_stats');
    }
};


