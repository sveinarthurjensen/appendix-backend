<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten ContractEvent (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_events', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('contract_id')->nullable();  // Tilknyttet Contract
            $table->string('event_type', 64)->nullable();  // Type kontrakthendelse
            $table->text('actor_user_id')->nullable();  // Hvem utløste (admin user id / system)
            $table->text('actor_email')->nullable();  // E-post til actor
            $table->string('actor_role', 64)->nullable();
            $table->text('detail')->nullable();  // Ytterligere kontekst (f.eks. signeringsmetode, versjon)
            $table->text('previous_status')->nullable();
            $table->text('new_status')->nullable();
            $table->decimal('version_number', 18, 4)->nullable();  // Gjeldende versjon ved hendelsen
            $table->index('contract_id');
            $table->index('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_events');
    }
};
