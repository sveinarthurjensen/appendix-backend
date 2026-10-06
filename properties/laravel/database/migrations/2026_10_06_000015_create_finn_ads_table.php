<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten FinnAd (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finn_ads', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('property_id')->nullable();  // Tilknyttet eiendom
            $table->text('finn_code')->nullable();  // FINN-kode
            $table->text('title')->nullable();
            $table->text('url')->nullable();
            $table->string('status', 64)->default('active')->nullable();
            $table->decimal('views', 18, 4)->nullable();
            $table->decimal('favorites', 18, 4)->nullable();
            $table->decimal('inquiries', 18, 4)->nullable();
            $table->date('published_date')->nullable();
            $table->date('expires_date')->nullable();
            $table->decimal('price', 18, 4)->nullable();
            $table->decimal('ad_cost', 18, 4)->nullable();
            $table->timestampTz('last_sync')->nullable();
            $table->index('property_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finn_ads');
    }
};
