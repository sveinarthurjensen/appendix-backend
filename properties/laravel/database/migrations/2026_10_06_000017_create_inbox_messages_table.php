<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten InboxMessage (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->string('message_type', 64)->nullable();  // Meldingstype
            $table->string('direction', 64)->nullable();  // Retning
            $table->text('from_address')->nullable();  // Avsender (e-post/telefon/sender)
            $table->text('to_address')->nullable();  // Mottaker (kommaseparert ved flere)
            $table->text('subject')->nullable();  // Emne (e-post)
            $table->text('body_text')->nullable();  // Meldingstekst
            $table->text('mailbox')->nullable();  // Postboks (f.eks. post@aprop.no)
            $table->timestampTz('sent_date')->nullable();  // Sendt tidspunkt (ISO)
            $table->text('sent_by')->nullable();  // Hvem sendte (admin navn)
            $table->string('status', 64)->default('ny')->nullable();  // Lestatus
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
