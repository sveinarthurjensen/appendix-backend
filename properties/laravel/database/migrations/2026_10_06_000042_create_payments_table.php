<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Payment (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('booking_id')->nullable();  // Tilknyttet booking
            $table->text('contract_id')->nullable();  // Tilknyttet kontrakt
            $table->text('tenant_id')->nullable();  // Leietaker ID
            $table->text('property_id')->nullable();  // Eiendom ID
            $table->decimal('amount', 18, 4)->nullable();  // Beløp
            $table->string('payment_type', 64)->nullable();  // Type betaling
            $table->string('payment_method', 64)->nullable();  // Betalingsmetode
            $table->string('status', 64)->default('venter')->nullable();
            $table->date('due_date')->nullable();  // Forfallsdato
            $table->date('paid_date')->nullable();  // Betalingsdato
            $table->text('invoice_number')->nullable();  // Fakturanummer
            $table->text('description')->nullable();  // Beskrivelse
            $table->text('receipt_url')->nullable();  // Kvittering
            $table->index('booking_id');
            $table->index('contract_id');
            $table->index('tenant_id');
            $table->index('property_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
