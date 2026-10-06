<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten UserInvite (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invites', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('token')->nullable();  // Engangs invite-token (bæres gjennom BankID-flyten via cookie)
            $table->text('short_code')->nullable();  // Kort invite-kode (6-8 tegn) for /i/<kode>-redirect til accept-invite
            $table->text('target_user_id')->nullable();  // Resolvert User id (settes når brukeren er registrert via inviteUser)
            $table->text('name')->nullable();  // Fullt navn på invitert
            $table->text('email')->nullable();  // E-post (kontoident for BankID/SSO-innlogging)
            $table->text('phone')->nullable();  // Mobil (mottar SMS med BankID-lenke)
            $table->string('role', 64)->nullable();  // Valgt rolle (lagres på User.role ved BankID-kobling). 'guest' = ekstern Min side-bruker fra nettsidehenvendelse (auto-godkjent).
            $table->text('nin_hash')->nullable();  // SHA-256 av fødselsnummer — bindes til invitasjonen ved BankID-aksept
            $table->string('status', 64)->default('draft')->nullable();
            $table->timestampTz('expires_at')->nullable();  // Utløpstid for token (standard 7 dager)
            $table->timestampTz('used_at')->nullable();  // Når invite-token ble brukt i BankID-flyten
            $table->text('used_flow_id')->nullable();  // OidcAuthFlow id som koblet invite
            $table->text('created_by_id')->nullable();
            $table->text('created_by_name')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('approved_at')->nullable();  // Når admin godkjente utkast og sendte (eller auto for guest)
            $table->text('approved_by_id')->nullable();
            $table->text('email_subject')->nullable();  // Godkjent e-postutkast (emne)
            $table->text('email_body')->nullable();  // Godkjent e-postutkast (HTML)
            $table->text('sms_body')->nullable();  // Godkjent SMS-utkast (inkl. BankID-lenke)
            $table->timestampTz('registration_sent_at')->nullable();  // Når forhåndsprovisjonering ble gjort
            $table->timestampTz('sms_sent_at')->nullable();
            $table->timestampTz('welcome_email_sent_at')->nullable();  // Når app-egen velkomst-e-post ble sendt ved registrering
            $table->index('token');
            $table->index('target_user_id');
            $table->index('email');
            $table->index('status');
            $table->index('used_flow_id');
            $table->index('created_by_id');
            $table->index('approved_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invites');
    }
};
