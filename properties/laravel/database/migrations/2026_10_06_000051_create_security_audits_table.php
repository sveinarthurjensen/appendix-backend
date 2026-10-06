<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten SecurityAudit (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audits', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->timestampTz('run_at')->nullable();  // Tidspunkt for kjøring
            $table->string('status', 64)->default('ok')->nullable();  // Samlet status for kjøringen
            $table->decimal('entities_checked', 18, 4)->nullable();  // Antall entiteter sjekket
            $table->decimal('issues_found', 18, 4)->nullable();  // Antall funn totalt
            $table->jsonb('findings')->nullable();  // Liste over funn med entitet, problemtype og alvorlighetsgrad
            $table->boolean('resolved')->default(false)->nullable();  // Markert som håndtert av admin
            $table->text('resolved_by')->nullable();  // Admin som markerte funnene håndtert
            $table->timestampTz('resolved_at')->nullable();
            $table->string('trigger', 64)->default('scheduled')->nullable();  // Hvordan kjøringen ble utløst
            $table->text('manifest_version')->nullable();  // Versjon av RLS-manifestet som ble analysert
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audits');
    }
};
