<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ChatMessage (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('session_id')->nullable();  // Unik sesjons-ID for chat-samtalen
            $table->text('sender_name')->nullable();  // Navn på avsender
            $table->text('sender_email')->nullable();  // E-post til avsender
            $table->text('sender_phone')->nullable();  // Telefon til avsender
            $table->text('message')->nullable();  // Meldingsinnhold
            $table->string('direction', 64)->nullable();  // Retning på meldingen
            $table->string('channel', 64)->default('chat')->nullable();  // Kanal
            $table->string('status', 64)->default('ulest')->nullable();
            $table->text('property_interest')->nullable();  // Eiendom henvendelsen gjelder
            $table->text('admin_note')->nullable();  // Intern notat fra admin
            $table->index('session_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
