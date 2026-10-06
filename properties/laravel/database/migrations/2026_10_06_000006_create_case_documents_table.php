<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten CaseDocument (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_documents', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('case_id')->nullable();  // Tilknyttet sak
            $table->text('name')->nullable();  // Dokumentnavn
            $table->string('document_type', 64)->default('innkommende')->nullable();  // Dokumenttype
            $table->text('url')->nullable();  // Fil-URL
            $table->text('description')->nullable();  // Beskrivelse
            $table->text('content_text')->nullable();  // Ekstrahert tekst for AI-kontekst
            $table->text('source_party')->nullable();  // Hvem leverte dokumentet
            $table->timestampTz('uploaded_date')->nullable();  // Opplastet dato
            $table->boolean('is_new')->default(true)->nullable();  // Markert som nytt (for AI endringsforslag)
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_documents');
    }
};
