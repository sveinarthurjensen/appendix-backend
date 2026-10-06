<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten BudgetItem (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
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
            $table->decimal('year', 18, 4)->nullable();  // Budsjettår
            $table->string('category', 64)->nullable();  // Utgiftskategori
            $table->text('description')->nullable();  // Beskrivelse av estimert kostnad
            $table->decimal('amount', 18, 4)->nullable();  // Beløp (budsjett/estimat)
            $table->text('notes')->nullable();  // Notater
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
