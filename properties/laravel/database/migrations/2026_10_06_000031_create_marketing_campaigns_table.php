<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten MarketingCampaign (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Kampanjenavn
            $table->string('type', 64)->nullable();  // Type kampanje
            $table->string('status', 64)->default('draft')->nullable();
            $table->string('target_audience', 64)->nullable();  // Målgruppe
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 18, 4)->nullable();  // Budsjett i NOK
            $table->decimal('spent', 18, 4)->nullable();  // Brukt beløp
            $table->decimal('impressions', 18, 4)->nullable();  // Visninger
            $table->decimal('clicks', 18, 4)->nullable();  // Klikk
            $table->decimal('conversions', 18, 4)->nullable();  // Konverteringer
            $table->text('external_id')->nullable();  // Ekstern ID (Google/FINN)
            $table->text('property_id')->nullable();  // Tilknyttet eiendom
            $table->text('notes')->nullable();
            $table->index('status');
            $table->index('external_id');
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
