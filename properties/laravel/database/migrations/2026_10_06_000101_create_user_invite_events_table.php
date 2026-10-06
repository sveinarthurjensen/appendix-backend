<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten UserInviteEvent (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invite_events', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('invite_id')->nullable();  // Referanse til UserInvite
            $table->text('invite_email')->nullable();  // Mottakers e-post (denormalisert for visning)
            $table->string('event_type', 64)->nullable();  // Livssyklus-hendelse
            $table->text('actor_name')->nullable();  // Hvem utløste hendelsen (admin e-post / mottaker / system)
            $table->text('detail')->nullable();  // Ytterligere kontekst
            $table->index('invite_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invite_events');
    }
};
