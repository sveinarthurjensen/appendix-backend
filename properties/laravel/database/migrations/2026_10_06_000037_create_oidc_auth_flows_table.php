<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten OidcAuthFlow (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oidc_auth_flows', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('flow_id')->nullable();  // Intern flow-id
            $table->text('auth_code')->nullable();  // Engangs autorisasjonskode utstedt av broen (byttes i oidcToken)
            $table->text('state')->nullable();  // Base44 OIDC state (pass-through tilbake til Base44)
            $table->text('nonce')->nullable();  // Base44 OIDC nonce (bindes i id_token)
            $table->text('client_id')->nullable();
            $table->text('redirect_uri')->nullable();  // Base44 callback-URI
            $table->text('scope')->nullable();
            $table->text('response_type')->nullable();
            $table->text('code_challenge')->nullable();
            $table->text('code_challenge_method')->nullable();
            $table->text('signicat_state')->nullable();  // HMAC-signert state sendt til Signicat
            $table->text('signicat_nonce')->nullable();
            $table->text('invite_token')->nullable();  // UserInvite-token baart via cookie fra /accept-invite (tom for vanlige logins)
            $table->string('status', 64)->default('pending')->nullable();
            $table->string('source', 64)->default('')->nullable();
            $table->text('user_id')->nullable();  // Resolvert Base44-bruker id
            $table->text('email')->nullable();
            $table->text('name')->nullable();
            $table->text('nin_hash')->nullable();  // SHA-256 hex av fnr
            $table->text('acr')->nullable();
            $table->jsonb('amr')->nullable();
            $table->text('access_token')->nullable();  // Utdelt access_token (brukes av oidcUserinfo)
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('used_at')->nullable();  // Naar auth_code ble loest inn i oidcToken
            $table->text('ip')->nullable();
            $table->text('error')->nullable();
            $table->index('flow_id');
            $table->index('client_id');
            $table->index('status');
            $table->index('user_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oidc_auth_flows');
    }
};
