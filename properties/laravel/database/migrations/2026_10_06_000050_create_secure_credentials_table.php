<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten SecureCredential (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secure_credentials', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('company_id')->nullable();
            $table->text('name')->nullable();
            $table->text('category')->default('annet')->nullable();
            $table->text('username')->nullable();
            $table->text('encrypted_password')->nullable();
            $table->text('url')->nullable();
            $table->boolean('two_factor_enabled')->default(false)->nullable();
            $table->text('two_factor_method')->nullable();
            $table->text('recovery_codes')->nullable();
            $table->text('notes')->nullable();
            $table->text('access_level')->default('admin_only')->nullable();
            $table->boolean('is_sync_token')->default(false)->nullable();
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secure_credentials');
    }
};
