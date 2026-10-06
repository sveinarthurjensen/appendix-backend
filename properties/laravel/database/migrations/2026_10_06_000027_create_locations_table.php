<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Location (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('source_app')->nullable();  // Kilde-app (f.eks. prime-leie, medhjelp)
            $table->text('company_id')->nullable();  // Selskaps-ID fra kildesystem
            $table->text('external_id')->nullable();  // Ekstern ID fra kildesystem
            $table->text('name')->nullable();  // Navn på lokasjon
            $table->string('type', 64)->nullable();  // Type lokasjon
            $table->text('street')->nullable();  // Gateadresse
            $table->text('postal_code')->nullable();  // Postnummer
            $table->text('city')->nullable();  // By/sted
            $table->decimal('area_sqm', 18, 4)->nullable();  // Areal i kvadratmeter
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->text('description')->nullable();  // Beskrivelse
            $table->decimal('latitude', 18, 4)->nullable();
            $table->decimal('longitude', 18, 4)->nullable();
            $table->timestampTz('last_sync')->nullable();  // Sist synkronisert
            $table->jsonb('sync_metadata')->nullable();  // Metadata fra synkronisering
            $table->index('company_id');
            $table->index('external_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
