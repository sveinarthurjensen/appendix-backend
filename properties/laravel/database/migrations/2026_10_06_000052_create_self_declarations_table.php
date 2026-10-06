<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten SelfDeclaration (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_declarations', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('booking_id')->nullable();  // Referanse til Booking-entiteten
            $table->text('guest_id')->nullable();  // Gjestens bruker-ID
            $table->text('property_id')->nullable();  // Eiendom ID
            $table->date('check_in_date')->nullable();  // Innsjekk dato
            $table->date('check_out_date')->nullable();  // Utsjekk dato
            $table->decimal('num_guests', 18, 4)->nullable();  // Antall gjester
            $table->text('purpose')->nullable();  // Formål med oppholdet
            $table->boolean('confirmed_responsible_use')->default(false)->nullable();  // Bekrefter forsvarlig behandling av hytta
            $table->boolean('confirmed_house_rules')->default(false)->nullable();  // Bekrefter at husregler følges
            $table->boolean('confirmed_terms')->default(false)->nullable();  // Aksepterer vilkårene
            $table->timestampTz('submitted_at')->nullable();  // Tidspunkt for innsending
            $table->index('booking_id');
            $table->index('guest_id');
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_declarations');
    }
};
