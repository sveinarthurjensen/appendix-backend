<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten WebAuthnChallenge (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_authn_challenges', function (Blueprint $table) {
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
            $table->text('challenge')->nullable();  // Base64url challenge
            $table->string('purpose', 64)->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->boolean('used')->default(false)->nullable();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_authn_challenges');
    }
};
