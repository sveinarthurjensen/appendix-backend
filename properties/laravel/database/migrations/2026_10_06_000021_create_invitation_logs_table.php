<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten InvitationLog (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_logs', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('user_role_id')->nullable();  // Brukerrolle ID
            $table->text('user_email')->nullable();  // Mottakers e-post
            $table->string('sent_via', 64)->nullable();  // Sendt via
            $table->timestampTz('sent_date')->nullable();  // Sendedato
            $table->string('message_type', 64)->nullable();  // Type melding
            $table->string('status', 64)->default('sendt')->nullable();
            $table->text('error_message')->nullable();  // Feilmelding hvis sending feilet
            $table->index('user_role_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_logs');
    }
};
