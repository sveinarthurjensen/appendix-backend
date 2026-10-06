<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Expense (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
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
            $table->date('date')->nullable();  // Dato for utgift
            $table->decimal('year', 18, 4)->nullable();  // Regnskapsår
            $table->string('category', 64)->nullable();  // Utgiftskategori
            $table->text('description')->nullable();  // Beskrivelse
            $table->decimal('amount', 18, 4)->nullable();  // Beløp
            $table->decimal('vat_amount', 18, 4)->nullable();  // MVA beløp
            $table->text('supplier')->nullable();  // Leverandør
            $table->text('invoice_number')->nullable();  // Fakturanummer
            $table->text('receipt_url')->nullable();  // Kvittering/faktura fil
            $table->string('payment_method', 64)->nullable();  // Betalingsmetode
            $table->boolean('is_deductible')->default(true)->nullable();  // Fradragsberettiget
            $table->string('tax_category', 64)->nullable();  // Skattekategori
            $table->text('notes')->nullable();  // Notater
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
