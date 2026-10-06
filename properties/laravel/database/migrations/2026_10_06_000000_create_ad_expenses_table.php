<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten AdExpense (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_expenses', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('campaign_id')->nullable();  // Kampanje ID
            $table->text('google_account_id')->nullable();  // Google Ads konto
            $table->date('date')->nullable();
            $table->decimal('amount', 18, 4)->nullable();  // Beløp
            $table->text('currency')->default('NOK')->nullable();
            $table->string('platform', 64)->nullable();
            $table->text('payment_card_last_four')->nullable();
            $table->text('receipt_url')->nullable();  // Kvittering URL
            $table->text('invoice_number')->nullable();
            $table->decimal('impressions', 18, 4)->nullable();
            $table->decimal('clicks', 18, 4)->nullable();
            $table->decimal('conversions', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->index('campaign_id');
            $table->index('google_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_expenses');
    }
};
