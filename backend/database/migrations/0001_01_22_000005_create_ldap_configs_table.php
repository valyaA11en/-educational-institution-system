<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ldap_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(389);
            $table->string('base_dn');
            $table->string('bind_dn')->nullable();
            $table->string('bind_password')->nullable(); // Encrypted
            $table->string('user_filter')->nullable();
            $table->jsonb('attribute_mapping')->default('{}');
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ldap_configs');
    }
};


