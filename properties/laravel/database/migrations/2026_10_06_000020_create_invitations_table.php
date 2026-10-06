<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Invitation (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('email')->nullable();  // Invitert e-post (kontoident for registrering/BankID)
            $table->text('full_name')->nullable();  // Fullt navn på invitert
            $table->text('mobile')->nullable();  // Mobilnummer (mottar SMS med registreringslenke)
            $table->string('role', 64)->default('leietaker')->nullable();  // Valgt rolle (settes på User ved onboarding)
            $table->decimal('niva', 18, 4)->default(1)->nullable();  // Identitetsnivå (1 = standard, 2 = forhåndsregistrert fnr)
            $table->text('national_id_hash')->nullable();  // SHA-256 av fødselsnummer — kun for Vei 3 (forhåndsregistrert fnr)
            $table->text('national_id_last4')->nullable();  // Siste 4 sifre av fødselsnummer (Vei 3)
            $table->text('token')->nullable();  // Engangs invitasjonstoken (bæres i /register?invite=...)
            $table->string('status', 64)->default('pending')->nullable();  // Livssyklus-status
            $table->text('invited_by')->nullable();  // Admin-brukerens id som opprettet invitasjonen
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('sent_at')->nullable();  // Når SMS/utsendelse ble sendt
            $table->text('registered_user_id')->nullable();  // Resolvert User id (settes ved autoApproveInvitedUser)
            $table->boolean('send_email')->default(false)->nullable();  // Om e-post skal sendes (kanal valgfri/støttes per mottaker)
            $table->boolean('send_sms')->default(true)->nullable();  // Om SMS skal sendes (hovedkanal)
            $table->index('email');
            $table->index('token');
            $table->index('status');
            $table->index('registered_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
