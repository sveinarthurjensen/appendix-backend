<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten QRSession (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_sessions', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('session_id')->nullable();  // Ugjettbar sesjons-ID
            $table->string('status', 64)->default('pending')->nullable();
            $table->string('purpose', 64)->default('login')->nullable();  // Hva sesjonen brukes til (innlogging, dokumentsignering, BankID step-up)
            $table->text('document_type')->nullable();  // Dokumenttype som signeres (f.eks. contract) - kun for purpose=signing
            $table->text('document_id')->nullable();  // ID på dokumentet som signeres - kun for purpose=signing
            $table->string('signer_role', 64)->nullable();  // Hvem signerer - kun for purpose=signing
            $table->text('payload_hash')->nullable();  // SHA-256 hash av dokumentinnhold som bindes til signeringen
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->text('approved_user_id')->nullable();  // Brukeren som godkjente (mobil)
            $table->timestampTz('approved_at')->nullable();  // Tidspunkt for godkjenning
            $table->text('approved_token')->nullable();  // App-JWT for PC-en; tømmes ved consume
            $table->timestampTz('consumed_at')->nullable();
            $table->text('mobile_user_id')->nullable();
            $table->text('bridge_preauth_token')->nullable();  // Engangs preauth-token for OIDC-broens QR-hurtigsti (settes ved godkjenning)
            $table->timestampTz('bridge_used_at')->nullable();  // Naar broen konsumerte preauth-token (engangs-bruk)
            $table->index('session_id');
            $table->index('status');
            $table->index('document_id');
            $table->index('approved_user_id');
            $table->index('mobile_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_sessions');
    }
};
