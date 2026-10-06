<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Insurance (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurances', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('property_id')->nullable();  // Eiendom ID
            $table->string('insurance_type', 64)->nullable();  // Type forsikring
            $table->string('rental_category', 64)->default('begge')->nullable();  // Gjelder for privat eller firma utleie
            $table->text('provider')->nullable();  // Forsikringsselskap
            $table->text('policy_number')->nullable();  // Polisenummer
            $table->decimal('coverage_amount', 18, 4)->nullable();  // Dekningsbeløp
            $table->decimal('deductible', 18, 4)->nullable();  // Egenandel
            $table->decimal('annual_premium', 18, 4)->nullable();  // Årlig premie
            $table->date('start_date')->nullable();  // Startdato
            $table->date('end_date')->nullable();  // Utløpsdato
            $table->boolean('auto_renew')->default(true)->nullable();  // Automatisk fornyelse
            $table->text('contact_person')->nullable();  // Kontaktperson hos forsikringsselskap
            $table->text('contact_phone')->nullable();  // Telefon
            $table->text('contact_email')->nullable();  // E-post
            $table->text('document_url')->nullable();  // Forsikringsdokument
            $table->text('notes')->nullable();  // Notater
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->index('property_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurances');
    }
};
