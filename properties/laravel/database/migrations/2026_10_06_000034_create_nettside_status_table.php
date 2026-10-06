<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten NettsideStatus (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nettside_status', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->decimal('feil_paa_rad', 18, 4)->default(0)->nullable();
            $table->text('navn')->nullable();
            $table->timestampTz('nede_siden')->nullable();
            $table->boolean('ok')->default(true)->nullable();
            $table->timestampTz('sist_sjekket')->nullable();
            $table->text('siste_feil')->nullable();
            $table->decimal('siste_status', 18, 4)->nullable();
            $table->decimal('svartid_ms', 18, 4)->nullable();
            $table->text('url')->nullable();  // Adressen som overvåkes
            $table->boolean('varslet_nede')->default(false)->nullable();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nettside_status');
    }
};
