<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Case (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('title')->nullable();  // Tittel på sak
            $table->text('case_number')->nullable();  // Saksnummer
            $table->string('case_type', 64)->default('henvendelse')->nullable();  // Sakstype
            $table->text('description')->nullable();  // Beskrivelse av saken
            $table->string('status', 64)->default('ny')->nullable();  // Status
            $table->string('priority', 64)->default('normal')->nullable();  // Prioritet
            $table->text('property_id')->nullable();  // Tilknyttet eiendom (valgfritt)
            $table->text('property_name')->nullable();  // Eiendomsnavn (for visning)
            $table->text('tenant_id')->nullable();  // Tilknyttet leietaker (valgfritt)
            $table->text('counterpart_name')->nullable();  // Motpart navn
            $table->text('counterpart_email')->nullable();  // Motpart e-post
            $table->text('counterpart_address')->nullable();  // Motpart adresse
            $table->text('assigned_to_id')->nullable();  // Saksbehandler (bruker ID)
            $table->text('assigned_to_name')->nullable();  // Saksbehandler navn
            $table->date('due_date')->nullable();  // Frist
            $table->text('resolution')->nullable();  // Løsning/avslutning
            $table->jsonb('tags')->nullable();  // Tags
            $table->index('status');
            $table->index('property_id');
            $table->index('tenant_id');
            $table->index('assigned_to_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
