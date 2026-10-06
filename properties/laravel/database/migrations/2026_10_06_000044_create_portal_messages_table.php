<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten PortalMessage (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_messages', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('case_id')->nullable();  // Tilknyttet Case (henvendelse) — tråd-id
            $table->text('case_number')->nullable();  // Saksnummer (denormalisert for visning på Min side)
            $table->text('subject')->nullable();  // Emne/tittel (denormalisert for visning)
            $table->text('user_email')->nullable();  // Gjestens e-post (identitet — brukes for scoping på Min side)
            $table->text('user_id')->nullable();  // Gjestens User id (hvis kjent ved opprettelse)
            $table->string('direction', 64)->nullable();  // Retning: innkommende (fra nettside/Min side) eller utgående (fra ansatt)
            $table->text('message')->nullable();  // Meldingsinnhold
            $table->string('channel', 64)->nullable();  // Hvor meldingen kom fra
            $table->text('sender_name')->nullable();  // Avsender navn (for visning)
            $table->string('status', 64)->default('ulest')->nullable();  // Lestatus
            $table->index('case_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_messages');
    }
};
