<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten LetterTemplate (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Navn på mal
            $table->string('category', 64)->default('generelt')->nullable();  // Kategori
            $table->text('subject')->nullable();  // Standard emne
            $table->text('body_template')->nullable();  // Maltekst med {{variabler}}
            $table->jsonb('variables')->nullable();  // Tilgjengelige variabler
            $table->boolean('is_active')->default(true)->nullable();  // Aktiv

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
