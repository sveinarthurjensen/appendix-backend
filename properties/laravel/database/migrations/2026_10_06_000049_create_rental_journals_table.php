<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten RentalJournal (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_journals', function (Blueprint $table) {
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
            $table->text('contract_id')->nullable();  // Kontrakt ID
            $table->string('rental_type', 64)->nullable();  // Type utleie
            $table->date('start_date')->nullable();  // Startdato
            $table->date('end_date')->nullable();  // Sluttdato
            $table->decimal('monthly_rent', 18, 4)->nullable();  // Månedlig leie
            $table->decimal('total_rent', 18, 4)->nullable();  // Total leie for perioden
            $table->decimal('deposit_amount', 18, 4)->nullable();  // Depositum
            $table->boolean('deposit_returned')->default(false)->nullable();  // Depositum tilbakebetalt
            $table->date('deposit_return_date')->nullable();  // Dato depositum returnert
            $table->decimal('deposit_deductions', 18, 4)->nullable();  // Trekk fra depositum
            $table->text('deposit_deduction_reason')->nullable();  // Årsak til trekk
            $table->text('finn_ad_code')->nullable();  // FINN annonse kode
            $table->text('finn_ad_url')->nullable();  // FINN annonse URL
            $table->text('finn_ad_screenshot')->nullable();  // Skjermbilde av FINN annonse
            $table->text('contract_document_url')->nullable();  // Lagret kontraktsdokument
            $table->text('move_in_inspection_url')->nullable();  // Innflyttingsprotokoll
            $table->text('move_out_inspection_url')->nullable();  // Utflyttingsprotokoll
            $table->text('notes')->nullable();  // Notater om leieforholdet
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->text('termination_reason')->nullable();  // Årsak til oppsigelse
            $table->decimal('year', 18, 4)->nullable();  // År for skatteformål
            $table->index('property_id');
            $table->index('tenant_id');
            $table->index('contract_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_journals');
    }
};
