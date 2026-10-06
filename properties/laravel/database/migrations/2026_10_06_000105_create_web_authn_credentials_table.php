<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten WebAuthnCredential (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_authn_credentials', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('user_id')->nullable();  // Bruker ID
            $table->text('credential_id')->nullable();  // WebAuthn credential ID (base64url)
            $table->text('public_key')->nullable();  // Offentlig nøkkel (base64url/PEM)
            $table->decimal('counter', 18, 4)->default(0)->nullable();  // Signatur-teller
            $table->jsonb('transports')->nullable();  // Autentikator-transports
            $table->text('device_label')->nullable();  // Valgfri enhetsbetegnelse
            $table->timestampTz('created_at')->nullable();
            $table->index('user_id');
            $table->index('credential_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_authn_credentials');
    }
};
