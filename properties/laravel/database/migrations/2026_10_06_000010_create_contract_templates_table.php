<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ContractTemplate (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Navn på malen
            $table->text('description')->nullable();  // Beskrivelse av malen
            $table->string('contract_type', 64)->default('langtid')->nullable();
            $table->boolean('is_office_template')->default(false)->nullable();  // Mal for kontorleie (hovedbygg Smestad)
            $table->text('default_lessor_company_id')->nullable();  // Standard utleiefirma for malen (LessorCompany ID)
            $table->text('default_invoice_bearer_company_id')->nullable();  // Standard fakturabærer for malen (LessorCompany ID)
            $table->string('duration_type', 64)->default('løpende')->nullable();
            $table->decimal('notice_period_months', 18, 4)->default(3)->nullable();
            $table->boolean('auto_renew')->default(true)->nullable();
            $table->decimal('renewal_period_months', 18, 4)->default(12)->nullable();
            $table->boolean('kpi_adjustment')->default(true)->nullable();  // Om leien reguleres i takt med KPI
            $table->decimal('deposit_months', 18, 4)->default(2)->nullable();  // Antall månedsleier i depositum
            $table->string('rental_scope', 64)->default('heltid')->nullable();  // Heltid eller deltid (f.eks. 2 dager/uke)
            $table->decimal('part_time_days_per_week', 18, 4)->nullable();  // Antall dager per uke for deltid
            $table->text('part_time_days')->nullable();  // Hvilke dager for deltid (f.eks. 'tirsdag, torsdag')
            $table->text('office_hours_from')->nullable();  // Kontortid fra (HH:MM)
            $table->text('office_hours_to')->nullable();  // Kontortid til (HH:MM)
            $table->text('office_hours_days')->nullable();  // Kontortid dager (f.eks. 'mandag–fredag')
            $table->decimal('included_parking_spots', 18, 4)->default(1)->nullable();  // Inkluderte parkeringsplasser i kontortiden
            $table->decimal('extra_parking_per_day', 18, 4)->nullable();  // Pris per ekstra parkeringsdag (utenfor kontortid)
            $table->boolean('meeting_room_access')->default(true)->nullable();  // Tilgang til felles møterom
            $table->decimal('meeting_room_per_hour', 18, 4)->nullable();  // Møterom pris per time
            $table->decimal('meeting_room_per_day', 18, 4)->nullable();  // Møterom pris per dag
            $table->jsonb('common_areas')->nullable();  // Fellesområder leietaker har tilgang til
            $table->text('shared_costs_description')->nullable();  // Hva felleskostnader dekker
            $table->text('house_rules')->nullable();  // Husordensregler for kontoret
            $table->jsonb('office_attachments')->nullable();  // Standardvedlegg for kontorkontrakter
            $table->text('terms')->nullable();  // Kontraktstekst
            $table->jsonb('tags')->nullable();  // Tags for sortering
            $table->boolean('is_active')->default(true)->nullable();
            $table->index('default_lessor_company_id');
            $table->index('default_invoice_bearer_company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
