<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Equipment (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Navn på utstyr/eiendel
            $table->text('description')->nullable();  // Beskrivelse
            $table->string('category', 64)->nullable();  // Kategori
            $table->string('equipment_class', 64)->nullable();  // Utstyrsklasse (for medisinsk utstyr)
            $table->text('property_id')->nullable();  // Tilknyttet eiendom
            $table->string('location_type', 64)->nullable();  // Plasseringstype
            $table->text('room')->nullable();  // Rom/lokasjon
            $table->string('ownership_type', 64)->nullable();  // Eierforhold
            $table->text('tenant_id')->nullable();  // Utleid til leietaker (hvis aktuelt)
            $table->text('serial_number')->nullable();  // Serienummer
            $table->text('model')->nullable();  // Modell
            $table->text('manufacturer')->nullable();  // Produsent
            $table->date('purchase_date')->nullable();  // Kjøpsdato
            $table->decimal('purchase_price', 18, 4)->nullable();  // Kjøpspris
            $table->decimal('depreciation_years', 18, 4)->nullable();  // Avskrivningstid (år)
            $table->decimal('current_value', 18, 4)->nullable();  // Nåværende bokført verdi
            $table->date('warranty_expires')->nullable();  // Garanti utløper
            $table->decimal('service_interval_months', 18, 4)->nullable();  // Serviceintervall (måneder)
            $table->date('last_service_date')->nullable();  // Siste service
            $table->date('next_service_date')->nullable();  // Neste service
            $table->text('service_provider_id')->nullable();  // Fast serviceleverandør
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->jsonb('documents')->nullable();  // Dokumenter (manualer, sertifikater)
            $table->jsonb('images')->nullable();  // Bilder
            $table->text('notes')->nullable();  // Notater
            $table->index('property_id');
            $table->index('tenant_id');
            $table->index('service_provider_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
