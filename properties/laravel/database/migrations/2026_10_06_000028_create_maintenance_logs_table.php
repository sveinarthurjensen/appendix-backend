<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten MaintenanceLog (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_logs', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('title')->nullable();  // Tittel
            $table->text('description')->nullable();  // Beskrivelse av utført arbeid
            $table->string('type', 64)->nullable();  // Type
            $table->text('property_id')->nullable();  // Eiendom
            $table->text('equipment_id')->nullable();  // Utstyr (hvis aktuelt)
            $table->text('schedule_id')->nullable();  // Tilknyttet planlagt service
            $table->date('performed_date')->nullable();  // Utført dato
            $table->text('performed_by')->nullable();  // Utført av (navn)
            $table->text('service_partner_id')->nullable();  // Serviceleverandør
            $table->string('result', 64)->nullable();  // Resultat
            $table->text('findings')->nullable();  // Funn/avvik
            $table->text('actions_taken')->nullable();  // Tiltak utført
            $table->boolean('follow_up_required')->default(false)->nullable();  // Krever oppfølging
            $table->date('follow_up_date')->nullable();  // Oppfølgingsdato
            $table->decimal('cost', 18, 4)->nullable();  // Kostnad
            $table->decimal('hours_spent', 18, 4)->nullable();  // Timer brukt
            $table->jsonb('photos_before')->nullable();  // Bilder før
            $table->jsonb('photos_after')->nullable();  // Bilder etter
            $table->jsonb('documents')->nullable();  // Dokumenter/rapporter
            $table->text('signature_url')->nullable();  // Signatur ved utførelse
            $table->text('notes')->nullable();  // Notater
            $table->index('property_id');
            $table->index('equipment_id');
            $table->index('schedule_id');
            $table->index('service_partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
