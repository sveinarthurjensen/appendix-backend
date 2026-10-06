<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten MarketingContact (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_contacts', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->string('type', 64)->nullable();  // Type kontakt
            $table->text('first_name')->nullable();
            $table->text('last_name')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->string('source', 64)->nullable();  // Kilde
            $table->text('campaign_id')->nullable();  // Tilknyttet kampanje
            $table->text('tenant_id')->nullable();  // Tilknyttet leietaker
            $table->boolean('consent_marketing')->default(false)->nullable();  // Samtykke til markedsføring
            $table->date('consent_date')->nullable();
            $table->jsonb('tags')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 64)->default('active')->nullable();
            $table->index('email');
            $table->index('campaign_id');
            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_contacts');
    }
};
