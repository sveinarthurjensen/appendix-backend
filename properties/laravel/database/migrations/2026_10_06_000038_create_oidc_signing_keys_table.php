<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten OidcSigningKey (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oidc_signing_keys', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('kid')->nullable();  // Key ID (brukt i JWT header + JWKS)
            $table->text('private_pem')->nullable();  // PKCS8 privat noekkel (PEM) - generert server-side, lagret her
            $table->jsonb('public_jwk')->nullable();  // Offentlig noekkel som JWK (publiseres i oidcJwks)
            $table->boolean('active')->default(true)->nullable();
            $table->timestampTz('created_at')->nullable();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oidc_signing_keys');
    }
};
