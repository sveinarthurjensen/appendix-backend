<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten DataBackup (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_backups', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->timestampTz('backup_date')->nullable();  // Dato og tid for backup
            $table->string('backup_type', 64)->nullable();  // Type backup
            $table->text('file_name')->nullable();  // Filnavn for backup
            $table->decimal('file_size', 18, 4)->nullable();  // Filstørrelse i bytes
            $table->decimal('entity_count', 18, 4)->nullable();  // Antall entiteter i backupen
            $table->decimal('record_count', 18, 4)->nullable();  // Totalt antall poster
            $table->string('status', 64)->default('in_progress')->nullable();  // Status på backup
            $table->jsonb('destination_results')->nullable();  // Resultater fra ulike destinasjoner
            $table->text('created_by_name')->nullable();  // Navn på bruker som startet backup
            $table->text('error_message')->nullable();  // Feilmelding hvis backup feilet
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_backups');
    }
};
