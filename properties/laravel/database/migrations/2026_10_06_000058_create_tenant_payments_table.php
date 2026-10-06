<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten TenantPayment (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payments', function (Blueprint $table) {
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
            $table->text('contract_id')->nullable();  // Kontrakt ID
            $table->decimal('amount', 18, 4)->nullable();  // Beløp
            $table->string('type', 64)->nullable();  // Type betaling
            $table->text('period')->nullable();  // Periode (f.eks. Januar 2024)
            $table->date('due_date')->nullable();  // Forfallsdato
            $table->date('paid_date')->nullable();  // Betalingsdato
            $table->string('status', 64)->default('venter')->nullable();
            $table->text('payment_reference')->nullable();  // Betalingsreferanse/KID
            $table->text('receipt_url')->nullable();  // Kvittering
            $table->text('notes')->nullable();  // Notater
            $table->index('tenant_id');
            $table->index('property_id');
            $table->index('contract_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payments');
    }
};
