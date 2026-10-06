<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten TenantOffer (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_offers', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('tenant_id')->nullable();  // Leietaker ID
            $table->text('property_id')->nullable();  // Eiendom ID
            $table->text('title')->nullable();  // Tittel på tilbud
            $table->text('description')->nullable();  // Beskrivelse av tilbudet
            $table->string('offer_type', 64)->nullable();  // Type tilbud
            $table->decimal('discount_percent', 18, 4)->nullable();  // Rabattprosent
            $table->decimal('discount_amount', 18, 4)->nullable();  // Rabattbeløp (fast)
            $table->date('valid_from')->nullable();  // Gyldig fra
            $table->date('valid_until')->nullable();  // Gyldig til
            $table->string('status', 64)->default('pending')->nullable();
            $table->text('terms')->nullable();  // Vilkår for tilbudet
            $table->timestampTz('accepted_date')->nullable();
            $table->index('tenant_id');
            $table->index('property_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_offers');
    }
};
