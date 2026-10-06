<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Tenant (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('first_name')->nullable();  // Fornavn
            $table->text('last_name')->nullable();  // Etternavn
            $table->text('email')->nullable();  // E-post
            $table->text('phone')->nullable();  // Telefonnummer
            $table->text('address')->nullable();  // Hjemmeadresse
            $table->text('city')->nullable();  // By
            $table->text('postal_code')->nullable();  // Postnummer
            $table->text('country')->nullable();  // Land
            $table->text('id_number')->nullable();  // Personnummer/ID
            $table->text('id_document_url')->nullable();  // Kopi av ID
            $table->text('notes')->nullable();  // Notater om leietaker
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
