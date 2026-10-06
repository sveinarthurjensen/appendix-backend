<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten TaxReport (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_reports', function (Blueprint $table) {
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
            $table->decimal('year', 18, 4)->nullable();  // Skatteår
            $table->decimal('total_rental_income', 18, 4)->nullable();  // Total leieinntekt
            $table->decimal('total_other_income', 18, 4)->nullable();  // Andre inntekter
            $table->decimal('total_maintenance_expenses', 18, 4)->nullable();  // Vedlikeholdskostnader
            $table->decimal('total_operating_expenses', 18, 4)->nullable();  // Driftskostnader
            $table->decimal('total_other_expenses', 18, 4)->nullable();  // Andre utgifter
            $table->decimal('depreciation', 18, 4)->nullable();  // Avskrivninger
            $table->decimal('net_income', 18, 4)->nullable();  // Netto inntekt
            $table->decimal('days_rented', 18, 4)->nullable();  // Antall utleiedager
            $table->decimal('days_personal_use', 18, 4)->nullable();  // Antall dager egen bruk
            $table->decimal('rental_percentage', 18, 4)->nullable();  // Utleieandel i prosent
            $table->string('status', 64)->default('utkast')->nullable();
            $table->text('notes')->nullable();  // Notater
            $table->text('report_url')->nullable();  // Generert rapport
            $table->index('property_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_reports');
    }
};
