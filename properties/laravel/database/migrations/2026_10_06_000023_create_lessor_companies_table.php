<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten LessorCompany (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessor_companies', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Utleiefirma / selskap
            $table->text('org_number')->nullable();  // Organisasjonsnummer
            $table->string('industry', 64)->default('helse')->nullable();  // Bransje
            $table->decimal('ownership_share', 18, 4)->nullable();  // Eierandel (%)
            $table->decimal('employees', 18, 4)->nullable();  // Antall ansatte
            $table->decimal('revenue_m', 18, 4)->nullable();  // Omsetning (millioner NOK)
            $table->string('compliance_status', 64)->default('compliant')->nullable();  // Compliance-status
            $table->string('active_status', 64)->default('aktiv')->nullable();  // Selskapets status
            $table->text('address')->nullable();  // Forretningsadresse
            $table->boolean('is_private')->default(false)->nullable();  // Privat utleier (f.eks. fysisk person, ikke AS)
            $table->text('contact_name')->nullable();  // Kontaktperson
            $table->text('contact_email')->nullable();  // Kontakt e-post
            $table->text('contact_phone')->nullable();  // Kontakt telefon
            $table->jsonb('default_for_regions')->nullable();  // Områder der dette selskapet er standard utleier (f.eks. 'smestad', 'sorlandet', 'geilo', 'svartediksveien')
            $table->boolean('is_invoice_bearer')->default(false)->nullable();  // Selskapet er fakturabærer for utleie (vises 'for regning' ved siden av utleier)
            $table->text('notes')->nullable();  // Notater

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessor_companies');
    }
};
