<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten LetterDraft (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_drafts', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('case_id')->nullable();  // Tilknyttet sak (valgfritt)
            $table->text('title')->nullable();  // Brevtittel
            $table->string('letter_type', 64)->default('generelt')->nullable();  // Brevtype
            $table->text('template_id')->nullable();  // Brukt mal
            $table->text('recipient_name')->nullable();  // Mottaker navn
            $table->text('recipient_address')->nullable();  // Mottaker adresse
            $table->text('recipient_email')->nullable();  // Mottaker e-post
            $table->text('sender_name')->nullable();  // Avsender navn
            $table->text('sender_title')->nullable();  // Avsender tittel/rolle
            $table->date('letter_date')->nullable();  // Brevdato
            $table->text('subject')->nullable();  // Emne
            $table->text('body')->nullable();  // Brevtekst (HTML)
            $table->text('signature_user_id')->nullable();  // Signatar (bruker ID)
            $table->text('signature_name')->nullable();  // Signator navn
            $table->string('signature_method', 64)->default('elektronisk')->nullable();  // Signeringsmetode
            $table->text('signed_url')->nullable();  // Signaturbilde URL
            $table->timestampTz('signed_date')->nullable();  // Signert dato
            $table->text('document_url')->nullable();  // Eksportert dokument URL
            $table->string('status', 64)->default('utkast')->nullable();  // Status
            $table->text('property_id')->nullable();  // Tilknyttet eiendom (valgfritt)
            $table->index('case_id');
            $table->index('template_id');
            $table->index('signature_user_id');
            $table->index('status');
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_drafts');
    }
};
