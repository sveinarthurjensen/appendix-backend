<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Invoice (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('invoice_number')->nullable();  // Fakturanummer
            $table->text('property_id')->nullable();  // Eiendom ID
            $table->text('tenant_id')->nullable();  // Leietaker ID
            $table->text('contract_id')->nullable();  // Kontrakt ID
            $table->date('invoice_date')->nullable();  // Fakturadato
            $table->date('due_date')->nullable();  // Forfallsdato
            $table->date('period_from')->nullable();  // Periode fra
            $table->date('period_to')->nullable();  // Periode til
            $table->jsonb('items')->nullable();  // Fakturalinjer
            $table->decimal('subtotal', 18, 4)->nullable();  // Sum eks. mva
            $table->decimal('vat_amount', 18, 4)->nullable();  // MVA beløp
            $table->decimal('total_amount', 18, 4)->nullable();  // Totalbeløp inkl. mva
            $table->text('kid_number')->nullable();  // KID-nummer
            $table->text('bank_account')->nullable();  // Bankkontonummer
            $table->string('status', 64)->default('utkast')->nullable();  // Fakturastatus
            $table->timestampTz('sent_date')->nullable();  // Sendt dato
            $table->date('paid_date')->nullable();  // Betalingsdato
            $table->decimal('paid_amount', 18, 4)->nullable();  // Innbetalt beløp
            $table->text('payment_reference')->nullable();  // Betalingsreferanse
            $table->decimal('reminder_count', 18, 4)->default(0)->nullable();  // Antall purringer sendt
            $table->date('last_reminder_date')->nullable();  // Siste purringsdato
            $table->text('unieconomy_id')->nullable();  // UniEconomy faktura-ID (for fremtidig integrasjon)
            $table->text('pdf_url')->nullable();  // PDF-fil URL
            $table->text('notes')->nullable();  // Interne notater
            $table->index('property_id');
            $table->index('tenant_id');
            $table->index('contract_id');
            $table->index('status');
            $table->index('unieconomy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
