<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ContractVersion (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_versions', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('contract_id')->nullable();  // Tilknyttet Contract
            $table->decimal('version_number', 18, 4)->nullable();  // Versjonsnummer (1 = opprinnelig)
            $table->text('terms')->nullable();  // Snapshot av kontraktstekst
            $table->text('terms_hash')->nullable();  // SHA-256 av terms+monthly_rent+start_date for denne versjonen
            $table->decimal('monthly_rent', 18, 4)->nullable();  // Leie snapshot
            $table->date('start_date')->nullable();  // Startdato snapshot
            $table->date('end_date')->nullable();  // Sluttdato snapshot
            $table->text('change_summary')->nullable();  // Hva som endret seg fra forrige versjon
            $table->string('version_type', 64)->default('opprettet')->nullable();  // Hvorfor versjonen ble opprettet
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->boolean('signed_by_landlord')->default(false)->nullable();  // Om utleier har signert denne versjonen
            $table->boolean('signed_by_tenant')->default(false)->nullable();  // Om leietaker har signert denne versjonen
            $table->timestampTz('signed_by_landlord_date')->nullable();
            $table->timestampTz('signed_by_tenant_date')->nullable();
            $table->text('created_by_name')->nullable();  // Opprettet av
            $table->index('contract_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_versions');
    }
};
