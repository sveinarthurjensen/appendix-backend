<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten PoolReading (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pool_readings', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('serial_number')->nullable();  // Aseko enhetens serienummer
            $table->text('unit_name')->nullable();  // Navn på bassengenhet
            $table->decimal('water_temperature', 18, 4)->nullable();  // Vanntemperatur (°C)
            $table->decimal('air_temperature', 18, 4)->nullable();  // Lufttemperatur (°C)
            $table->decimal('solar_temperature', 18, 4)->nullable();  // Solcelle/solar temperatur (°C)
            $table->decimal('water_temperature_required', 18, 4)->nullable();  // Ønsket vanntemperatur (°C)
            $table->decimal('ph', 18, 4)->nullable();  // pH-verdi
            $table->decimal('ph_required', 18, 4)->nullable();  // Ønsket pH-verdi
            $table->decimal('redox', 18, 4)->nullable();  // Redox (mV)
            $table->decimal('redox_required', 18, 4)->nullable();  // Ønsket redox (mV)
            $table->decimal('cl_free', 18, 4)->nullable();  // Fritt klor (mg/L)
            $table->decimal('cl_bounded', 18, 4)->nullable();  // Bundet klor (mg/L)
            $table->decimal('salinity', 18, 4)->nullable();  // Salinitet (kg/m³)
            $table->decimal('electrode_power', 18, 4)->nullable();  // Elektrode-effekt (g/h)
            $table->decimal('filter_flow_speed', 18, 4)->nullable();  // Filterstrømningshastighet (m³/t)
            $table->decimal('filter_pressure', 18, 4)->nullable();  // Filtertrykk (bar)
            $table->decimal('water_level', 18, 4)->nullable();  // Vannstand (cm)
            $table->text('mode')->nullable();  // Driftsmodus (auto/eco/off/on/party/winter)
            $table->text('filtration_speed')->nullable();  // Filtreringshastighet (boost/high/low/medium/off)
            $table->text('pool_flow')->nullable();  // Bassengstrømning (overflow/bottom)
            $table->text('water_level_state')->nullable();  // Vannstand-status (ok/filling/low/high)
            $table->text('electrolyzer_direction')->nullable();  // Elektrolyser-retning (left/right/waiting)
            $table->jsonb('raw_data')->nullable();  // Rådata fra Aseko API
            $table->boolean('pushed_to_smart_home')->default(false)->nullable();  // Om data ble pushet til smarthus
            $table->text('smart_home_response')->nullable();  // Svar fra smarthus-webhook

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pool_readings');
    }
};
