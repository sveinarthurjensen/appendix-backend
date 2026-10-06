<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Income (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
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
            $table->text('journal_id')->nullable();  // Utleiejournal ID
            $table->date('date')->nullable();  // Dato for inntekt
            $table->decimal('year', 18, 4)->nullable();  // Regnskapsår
            $table->string('category', 64)->nullable();  // Inntektskategori
            $table->string('rental_type', 64)->default('privat')->nullable();  // Type utleie - privat eller firma
            $table->text('description')->nullable();  // Beskrivelse
            $table->decimal('amount', 18, 4)->nullable();  // Beløp
            $table->string('payment_method', 64)->nullable();  // Betalingsmetode
            $table->boolean('is_taxable')->default(true)->nullable();  // Skattepliktig inntekt
            $table->text('reference')->nullable();  // Referanse/KID
            $table->text('receipt_url')->nullable();  // Dokumentasjon
            $table->text('notes')->nullable();  // Notater
            $table->index('property_id');
            $table->index('tenant_id');
            $table->index('journal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
