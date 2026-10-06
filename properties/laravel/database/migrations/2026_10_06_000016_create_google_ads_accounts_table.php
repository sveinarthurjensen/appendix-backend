<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten GoogleAdsAccount (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_accounts', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Kontonavn/enhet
            $table->text('account_id')->nullable();  // Google Ads konto-ID
            $table->string('entity_type', 64)->nullable();  // Type enhet
            $table->string('status', 64)->default('pending')->nullable();
            $table->text('access_token')->nullable();  // OAuth access token (kryptert)
            $table->text('refresh_token')->nullable();  // OAuth refresh token (kryptert)
            $table->timestampTz('last_sync')->nullable();
            $table->decimal('total_spend', 18, 4)->nullable();  // Total forbruk
            $table->jsonb('payment_cards')->nullable();
            $table->text('notes')->nullable();
            $table->index('account_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_accounts');
    }
};
