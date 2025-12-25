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
        Schema::create('doc_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type'); // order, decision, memo, protocol, statement, grade_sheet
            $table->string('name');
            $table->jsonb('schema_json');
            $table->string('file_template_key')->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_templates');
    }
};

