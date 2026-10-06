<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten LetterVersion (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_versions', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('letter_draft_id')->nullable();  // Tilknyttet brev
            $table->text('case_id')->nullable();  // Sak (for enkel oppslag)
            $table->decimal('version_number', 18, 4)->nullable();  // Versjonsnummer
            $table->text('subject')->nullable();  // Emne snapshot
            $table->text('body')->nullable();  // Brevtekst snapshot
            $table->text('change_summary')->nullable();  // Hva som endret seg
            $table->string('version_type', 64)->default('utkast')->nullable();  // Versjonstype
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->boolean('is_ai_generated')->default(false)->nullable();  // AI-generert
            $table->text('created_by_name')->nullable();  // Opprettet av
            $table->text('parent_version_id')->nullable();  // Basert på (for endringsforslag)
            $table->index('letter_draft_id');
            $table->index('case_id');
            $table->index('status');
            $table->index('parent_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_versions');
    }
};
