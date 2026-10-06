<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten PropertyAvailability (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_availabilities', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('property_id')->nullable();  // Referanse til Property-entiteten
            $table->date('date')->nullable();  // Datoen for tilgjengelighet
            $table->boolean('is_available')->default(false)->nullable();  // Sann hvis datoen er ledig, usann hvis opptatt
            $table->decimal('price_per_night', 18, 4)->nullable();  // Overstyrt pris per natt for denne datoen (NULL = bruk standardpris)
            $table->decimal('min_nights', 18, 4)->nullable();  // Minimum antall netter for denne datoen (NULL = ingen spesifikt krav)
            $table->text('note')->nullable();  // Fritt notat for datoen (f.eks. Airbnb-opplegg, sesong)
            $table->string('source', 64)->default('manual')->nullable();  // Hvor oppføringen kommer fra
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_availabilities');
    }
};
