<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten AuditEvent (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('event_type')->nullable();  // Type hendelse (oidc_authorize, oidc_signicat_login, qr_preauth_minted, oidc_token_issued, oidc_userinfo, provision_failed)
            $table->text('actor_user_id')->nullable();
            $table->text('actor_email')->nullable();
            $table->text('nin_hash')->nullable();
            $table->text('ip')->nullable();
            $table->text('detail')->nullable();
            $table->boolean('success')->default(true)->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->index('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
