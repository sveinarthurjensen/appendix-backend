<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten MaintenanceStaff (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_staffs', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Navn på vedlikeholdsperson/firma
            $table->text('company')->nullable();  // Firmanavn
            $table->text('org_number')->nullable();  // Organisasjonsnummer
            $table->text('email')->nullable();  // E-post
            $table->text('phone')->nullable();  // Telefon
            $table->text('address')->nullable();  // Adresse
            $table->jsonb('specialization')->nullable();  // Spesialområder
            $table->decimal('hourly_rate', 18, 4)->nullable();  // Timepris
            $table->text('contract_url')->nullable();  // Avtale/kontrakt
            $table->text('notes')->nullable();  // Notater
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->jsonb('assigned_properties')->nullable();  // Tilknyttede eiendommer
            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_staffs');
    }
};
