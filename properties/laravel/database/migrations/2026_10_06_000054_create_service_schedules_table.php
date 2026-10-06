<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ServiceSchedule (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_schedules', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('title')->nullable();  // Tittel på service/kontroll
            $table->text('description')->nullable();  // Beskrivelse
            $table->string('type', 64)->nullable();  // Type
            $table->text('property_id')->nullable();  // Eiendom
            $table->text('equipment_id')->nullable();  // Utstyr (hvis spesifikt)
            $table->text('service_partner_id')->nullable();  // Serviceleverandør
            $table->string('frequency', 64)->nullable();  // Frekvens
            $table->date('scheduled_date')->nullable();  // Planlagt / startdato
            $table->boolean('auto_generate')->default(false)->nullable();  // Opprett automatisk MaintenanceTask ved neste forfall
            $table->date('next_due_date')->nullable();  // Neste forfallsdato (beregnt ut fra frekvens)
            $table->timestampTz('last_generated_date')->nullable();  // Siste gang systemet opprettet en task fra denne planen
            $table->decimal('lead_days', 18, 4)->default(0)->nullable();  // Antall dager før forfall tasken opprettes
            $table->decimal('reminder_days_before', 18, 4)->default(14)->nullable();  // Påminnelse dager før
            $table->string('status', 64)->default('planlagt')->nullable();
            $table->date('completed_date')->nullable();  // Utført dato
            $table->text('completed_by')->nullable();  // Utført av
            $table->string('result', 64)->nullable();  // Resultat
            $table->decimal('cost', 18, 4)->nullable();  // Kostnad
            $table->text('invoice_url')->nullable();  // Faktura
            $table->text('report_url')->nullable();  // Rapport/sertifikat
            $table->text('notes')->nullable();  // Notater
            $table->boolean('is_recurring')->default(false)->nullable();  // Gjentagende oppgave
            $table->string('default_priority', 64)->default('medium')->nullable();  // Standard prioritet på genererte oppgaver
            $table->text('default_staff_id')->nullable();  // Standard tildelt vedlikeholdsperson på genererte oppgaver
            $table->index('property_id');
            $table->index('equipment_id');
            $table->index('service_partner_id');
            $table->index('status');
            $table->index('default_staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_schedules');
    }
};
