<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ParkingSpot (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_spots', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Navn på parkeringsplass (f.eks. P1, Garasje A)
            $table->text('spot_number')->nullable();  // Plassnummer
            $table->text('property_id')->nullable();  // Tilknyttet eiendom (valgfritt)
            $table->text('location_description')->nullable();  // Adresse / stedsbeskrivelse
            $table->decimal('latitude', 18, 4)->nullable();  // Breddegrad
            $table->decimal('longitude', 18, 4)->nullable();  // Lengdegrad
            $table->string('spot_type', 64)->default('utendørs')->nullable();  // Type parkeringsplass
            $table->boolean('has_charging')->default(false)->nullable();  // Har elbil-lader
            $table->boolean('has_roof')->default(false)->nullable();  // Overdekket
            $table->boolean('has_barrier')->default(false)->nullable();  // Tilknyttet bom/barriere
            $table->text('access_code')->nullable();  // PIN-kode / adgangskode
            $table->decimal('price_per_hour', 18, 4)->nullable();  // Pris per time (korttid)
            $table->decimal('price_per_day', 18, 4)->nullable();  // Pris per dag (korttid)
            $table->decimal('price_per_month', 18, 4)->nullable();  // Pris per måned (langtid)
            $table->text('payment_link')->nullable();  // Betalingslenke (Vipps, kort etc.)
            $table->text('qr_code_url')->nullable();  // QR-kode bilde URL
            $table->text('image')->nullable();  // Bilde av parkeringsplassen (f.eks. kartutsnitt)
            $table->string('status', 64)->default('ledig')->nullable();  // Status på plassen
            $table->text('notes')->nullable();  // Notater
            $table->index('property_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_spots');
    }
};
