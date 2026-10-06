<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ServicePartner (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_partners', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Firmanavn
            $table->text('contact_person')->nullable();  // Kontaktperson
            $table->string('category', 64)->nullable();  // Kategori
            $table->text('email')->nullable();  // E-post
            $table->text('phone')->nullable();  // Telefon
            $table->text('address')->nullable();  // Adresse
            $table->text('org_number')->nullable();  // Organisasjonsnummer
            $table->decimal('hourly_rate', 18, 4)->nullable();  // Timepris
            $table->boolean('emergency_available')->default(false)->nullable();  // Tilgjengelig for akutt utrykning
            $table->jsonb('assigned_properties')->nullable();  // Tilknyttede eiendommer
            $table->text('contract_url')->nullable();  // Avtale/kontrakt
            $table->decimal('rating', 18, 4)->nullable();  // Vurdering (1-5)
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->text('notes')->nullable();  // Notater
            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_partners');
    }
};
