<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten VaultEntry (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_entries', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('title')->nullable();  // Tittel på oppføring
            $table->string('entry_type', 64)->nullable();  // Type oppføring
            $table->text('property_id')->nullable();  // Tilknyttet eiendom
            $table->text('property_name')->nullable();  // Eiendomsnavn (for visning)
            $table->text('description')->nullable();  // Kort beskrivelse (ikke sensitiv)
            $table->text('encrypted_value')->nullable();  // Kryptert verdi (passord/kode)
            $table->text('iv')->nullable();  // Initialization vector for kryptering
            $table->text('username')->nullable();  // Brukernavn (hvis relevant)
            $table->text('url')->nullable();  // URL / login-side
            $table->text('encrypted_notes')->nullable();  // Krypterte notater
            $table->text('notes_iv')->nullable();  // IV for notater
            $table->jsonb('shared_with')->nullable();  // Bruker-IDer med delt tilgang
            $table->string('access_level', 64)->default('admin')->nullable();  // Tilgangsnivå
            $table->timestampTz('last_accessed')->nullable();  // Sist åpnet
            $table->text('last_accessed_by')->nullable();  // Sist åpnet av
            $table->boolean('is_active')->default(true)->nullable();  // Aktiv
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_entries');
    }
};
