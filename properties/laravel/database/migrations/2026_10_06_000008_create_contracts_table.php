<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Contract (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
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
            $table->text('property_id')->nullable();  // Eiendom ID
            $table->text('lessor_company_id')->nullable();  // Utleiefirma (LessorCompany ID) — selskapet som leier ut
            $table->text('invoice_bearer_company_id')->nullable();  // Fakturabærer (LessorCompany ID) — selskapet som står 'for regning' (f.eks. Appendix Holding)
            $table->text('tenant_id')->nullable();  // Leietaker ID (valgfri for kontorkontrakter)
            $table->string('contract_type', 64)->nullable();  // Type kontrakt
            $table->boolean('is_office')->default(false)->nullable();  // Kontorkontrakt (hovedbygg Smestad)
            $table->string('rental_scope', 64)->default('heltid')->nullable();  // Heltid eller deltid (f.eks. 2 dager/uke)
            $table->decimal('part_time_days_per_week', 18, 4)->nullable();  // Antall dager per uke for deltid
            $table->text('part_time_days')->nullable();  // Hvilke dager for deltid
            $table->text('office_hours_from')->nullable();  // Kontortid fra (HH:MM)
            $table->text('office_hours_to')->nullable();  // Kontortid til (HH:MM)
            $table->text('office_hours_days')->nullable();  // Kontortid dager (f.eks. 'mandag–fredag')
            $table->decimal('included_parking_spots', 18, 4)->default(1)->nullable();  // Inkluderte parkeringsplasser i kontortiden
            $table->decimal('extra_parking_per_day', 18, 4)->nullable();  // Pris per ekstra parkeringsdag (utenfor kontortid)
            $table->boolean('meeting_room_access')->default(true)->nullable();  // Tilgang til felles møterom
            $table->decimal('meeting_room_per_hour', 18, 4)->nullable();  // Møterom pris per time
            $table->decimal('meeting_room_per_day', 18, 4)->nullable();  // Møterom pris per dag
            $table->jsonb('common_areas')->nullable();  // Fellesområder leietaker har tilgang til
            $table->decimal('shared_costs_monthly', 18, 4)->nullable();  // Månedlig felleskostnad (i tillegg til leie)
            $table->text('shared_costs_description')->nullable();  // Hva felleskostnader dekker
            $table->text('house_rules')->nullable();  // Husordensregler
            $table->jsonb('office_attachments')->nullable();  // Vedlegg til kontorkontrakten
            $table->text('office_company_name')->nullable();  // Firma som leier (kontorkontrakt)
            $table->text('office_contact_name')->nullable();  // Kontaktperson i firmaet
            $table->text('office_contact_phone')->nullable();  // Kontaktperson telefon
            $table->text('office_contact_email')->nullable();  // Kontaktperson e-post
            $table->text('office_company_address')->nullable();  // Firmaadresse
            $table->text('office_space_name')->nullable();  // Kontorlokale navn/beskrivelse
            $table->string('duration_type', 64)->default('tidsbestemt')->nullable();  // Tidsbestemt eller løpende kontrakt
            $table->date('start_date')->nullable();  // Startdato
            $table->date('end_date')->nullable();  // Sluttdato (for tidsbestemt)
            $table->decimal('notice_period_months', 18, 4)->default(3)->nullable();  // Oppsigelsestid i måneder
            $table->boolean('auto_renew')->default(true)->nullable();  // Automatisk fornyelse
            $table->decimal('renewal_period_months', 18, 4)->default(12)->nullable();  // Fornyelsesperiode i måneder
            $table->date('termination_notice_date')->nullable();  // Dato oppsigelse må være mottatt
            $table->string('terminated_by', 64)->nullable();  // Hvem som sa opp
            $table->date('termination_date')->nullable();  // Faktisk oppsigelsesdato
            $table->date('move_out_date')->nullable();  // Utflyttingsdato
            $table->decimal('monthly_rent', 18, 4)->nullable();  // Månedlig leie
            $table->decimal('deposit_amount', 18, 4)->nullable();  // Depositum
            $table->text('terms')->nullable();  // Kontraktsvilkår
            $table->text('terms_hash')->nullable();  // SHA-256 av terms+monthly_rent+start_date — bindes til signaturen. Endring av terms etter signering markerer signaturen ugyldig.
            $table->decimal('current_version_number', 18, 4)->default(1)->nullable();  // Gjeldende versjon av kontraktsteksten (peker på ContractVersion)
            $table->text('document_url')->nullable();  // Kontraktsdokument
            $table->string('status', 64)->default('utkast')->nullable();
            $table->timestampTz('signed_by_tenant_date')->nullable();  // Signert av leietaker
            $table->timestampTz('signed_by_landlord_date')->nullable();  // Signert av utleier
            $table->text('tenant_signature_url')->nullable();  // Leietakers signatur
            $table->text('landlord_signature_url')->nullable();  // Utleiers signatur
            $table->string('tenant_signature_method', 64)->nullable();  // Signeringsmetode leietaker
            $table->string('landlord_signature_method', 64)->nullable();  // Signeringsmetode utleier
            $table->text('tenant_bankid_reference')->nullable();  // BankID referanse for leietaker
            $table->text('landlord_bankid_reference')->nullable();  // BankID referanse for utleier
            $table->text('tenant_bankid_identity')->nullable();  // Bekreftet identitet for leietaker (fra BankID/QR-verifisering)
            $table->text('landlord_bankid_identity')->nullable();  // Bekreftet identitet for utleier (fra BankID/QR-verifisering)
            $table->string('signature_verification_method', 64)->nullable();  // Hvordan signaturbekreftelsen ble gjort
            $table->text('signing_token')->nullable();  // Token for ekstern/token-basert verifisering av signert dokument
            $table->string('holding_transfer_status', 64)->default('not_transferred')->nullable();  // Overføringstilstand til sentralt avtalearkiv (Appendix Holding)
            $table->text('holding_case_number')->nullable();  // Saksnummer i sentralt avtalearkiv (Appendix Holding) — peker på hvor originalen nå bor
            $table->timestampTz('holding_transferred_at')->nullable();  // Tidspunkt for overføring til sentralt avtalearkiv
            $table->text('holding_transfer_error')->nullable();  // Feilmelding ved mislykket overføring til sentralt arkiv
            $table->index('booking_id');
            $table->index('property_id');
            $table->index('lessor_company_id');
            $table->index('invoice_bearer_company_id');
            $table->index('tenant_id');
            $table->index('status');
            $table->index('signing_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
