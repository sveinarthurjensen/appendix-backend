<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Booking (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
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
            $table->text('tenant_id')->nullable();  // Leietaker ID
            $table->string('booking_type', 64)->nullable();  // Type booking
            $table->string('booking_source', 64)->default('direkte')->nullable();  // Bookingkilde - hvor bookingen kom fra
            $table->text('external_booking_id')->nullable();  // Ekstern booking ID (fra Airbnb, Booking.com etc)
            $table->date('start_date')->nullable();  // Startdato
            $table->date('end_date')->nullable();  // Sluttdato
            $table->decimal('guests', 18, 4)->nullable();  // Antall gjester
            $table->decimal('total_price', 18, 4)->nullable();  // Totalpris
            $table->boolean('deposit_paid')->default(false)->nullable();  // Depositum betalt
            $table->string('status', 64)->default('forespørsel')->nullable();
            $table->string('payment_status', 64)->default('venter')->nullable();  // Betalingsstatus - 'ikke_relevant' for eksterne bookinger
            $table->text('special_requests')->nullable();  // Spesielle ønsker
            $table->text('check_in_time')->nullable();  // Faktisk innsjekk
            $table->text('check_out_time')->nullable();  // Faktisk utsjekk
            $table->text('contract_id')->nullable();  // Tilknyttet kontrakt
            $table->index('property_id');
            $table->index('tenant_id');
            $table->index('external_booking_id');
            $table->index('status');
            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
