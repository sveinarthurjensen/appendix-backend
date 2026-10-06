<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ParkingRental (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_rentals', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('spot_id')->nullable();  // Parkeringsplass ID
            $table->string('rental_type', 64)->nullable();  // Type utleie
            $table->text('tenant_id')->nullable();  // Eksisterende leietaker ID (valgfritt)
            $table->text('tenant_name')->nullable();  // Navn på leietaker
            $table->text('tenant_email')->nullable();  // E-post
            $table->text('tenant_phone')->nullable();  // Telefon
            $table->text('license_plate')->nullable();  // Registreringsnummer
            $table->date('start_date')->nullable();  // Startdato
            $table->date('end_date')->nullable();  // Sluttdato
            $table->text('start_time')->nullable();  // Starttidspunkt (korttid)
            $table->text('end_time')->nullable();  // Slutttidspunkt (korttid)
            $table->decimal('price', 18, 4)->nullable();  // Pris
            $table->text('payment_link')->nullable();  // Betalingslenke sendt til leietaker
            $table->string('payment_status', 64)->default('venter')->nullable();  // Betalingsstatus
            $table->string('status', 64)->default('aktiv')->nullable();  // Status
            $table->text('notes')->nullable();  // Notater
            $table->index('spot_id');
            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_rentals');
    }
};
