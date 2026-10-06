<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ParkingAccessDevice (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_access_devices', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Navn på enheten
            $table->text('spot_id')->nullable();  // Tilknyttet parkeringsplass
            $table->string('device_type', 64)->nullable();  // Type enhet
            $table->text('access_code')->nullable();  // PIN/kode for tilgang
            $table->text('webhook_url')->nullable();  // API-endepunkt for fjernkontroll (åpne/lukke)
            $table->string('webhook_method', 64)->default('POST')->nullable();  // HTTP-metode for webhook
            $table->text('webhook_payload')->nullable();  // JSON-payload for webhook (valgfritt)
            $table->text('manufacturer')->nullable();  // Produsent / leverandør
            $table->string('status', 64)->default('aktiv')->nullable();  // Status
            $table->text('notes')->nullable();  // Notater
            $table->index('spot_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_access_devices');
    }
};
