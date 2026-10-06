<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten MunicipalInfo (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_infos', function (Blueprint $table) {
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
            $table->text('municipality_name')->nullable();  // Kommunenavn
            $table->text('municipality_number')->nullable();  // Kommunenummer
            $table->jsonb('waste_collection')->nullable();  // Renovasjonskalender
            $table->jsonb('nearest_school')->nullable();  // Nærmeste skole
            $table->jsonb('nearest_kindergarten')->nullable();  // Nærmeste barnehage
            $table->jsonb('nearest_grocery')->nullable();  // Nærmeste matbutikk
            $table->jsonb('nearest_fire_station')->nullable();  // Nærmeste brannstasjon
            $table->jsonb('nearest_hospital')->nullable();  // Nærmeste sykehus/legevakt
            $table->jsonb('nearest_pharmacy')->nullable();  // Nærmeste apotek
            $table->jsonb('public_transport')->nullable();  // Kollektivtransport
            $table->jsonb('recreation')->nullable();  // Fritidsaktiviteter
            $table->jsonb('emergency_numbers')->nullable();  // Nødnumre
            $table->jsonb('municipal_services')->nullable();  // Kommunale tjenester
            $table->timestampTz('last_updated')->nullable();  // Sist oppdatert
            $table->text('notes')->nullable();  // Notater
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_infos');
    }
};
