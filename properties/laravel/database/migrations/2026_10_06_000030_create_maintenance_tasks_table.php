<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten MaintenanceTask (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tasks', function (Blueprint $table) {
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
            $table->text('staff_id')->nullable();  // Vedlikeholdsperson ID
            $table->text('service_partner_id')->nullable();  // Serviceleverandør (ekstern)
            $table->text('service_schedule_id')->nullable();  // Kildeplan (hvis generert fra ServiceSchedule)
            $table->string('source', 64)->default('manual')->nullable();  // Hvordan oppgaven oppsto
            $table->text('title')->nullable();  // Tittel på oppgave
            $table->text('description')->nullable();  // Beskrivelse
            $table->string('category', 64)->nullable();  // Kategori
            $table->string('priority', 64)->default('medium')->nullable();
            $table->string('status', 64)->default('ny')->nullable();
            $table->date('scheduled_date')->nullable();  // Planlagt dato
            $table->date('due_date')->nullable();  // Frist
            $table->date('completed_date')->nullable();  // Fullført dato
            $table->decimal('estimated_cost', 18, 4)->nullable();  // Estimert kostnad
            $table->decimal('actual_cost', 18, 4)->nullable();  // Faktisk kostnad
            $table->text('invoice_url')->nullable();  // Faktura fra leverandør
            $table->text('invoice_number')->nullable();  // Fakturanummer
            $table->jsonb('photos_before')->nullable();  // Bilder før
            $table->jsonb('photos_after')->nullable();  // Bilder etter
            $table->jsonb('attachments')->nullable();  // Vedlegg (bilder, fakturaer, protokoller)
            $table->jsonb('checklist')->nullable();  // Sjekkliste-punkter for rutineoppgaver
            $table->text('notes')->nullable();  // Notater
            $table->boolean('reported_by_tenant')->default(false)->nullable();  // Rapportert av leietaker
            $table->text('tenant_id')->nullable();  // Leietaker som rapporterte
            $table->index('property_id');
            $table->index('staff_id');
            $table->index('service_partner_id');
            $table->index('service_schedule_id');
            $table->index('status');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tasks');
    }
};
