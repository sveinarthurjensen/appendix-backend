<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten PropertyInfo (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_infos', function (Blueprint $table) {
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
            $table->text('wifi_name')->nullable();  // WiFi nettverk
            $table->text('wifi_password')->nullable();  // WiFi passord
            $table->text('door_code')->nullable();  // Dørkode
            $table->text('key_location')->nullable();  // Hvor nøkkel hentes
            $table->text('parking_info')->nullable();  // Parkeringsinformasjon
            $table->text('trash_info')->nullable();  // Søppelhåndtering
            $table->text('heating_info')->nullable();  // Oppvarming
            $table->text('appliances_guide')->nullable();  // Bruk av hvitevarer
            $table->jsonb('emergency_contacts')->nullable();  // Nødkontakter
            $table->text('house_rules')->nullable();  // Husregler
            $table->text('check_in_instructions')->nullable();  // Innsjekk instruksjoner
            $table->text('check_out_instructions')->nullable();  // Utsjekk instruksjoner
            $table->jsonb('nearby_places')->nullable();  // Nærliggende steder
            $table->jsonb('documents')->nullable();  // Dokumenter og manualer
            $table->text('custom_info')->nullable();  // Annen informasjon
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_infos');
    }
};
