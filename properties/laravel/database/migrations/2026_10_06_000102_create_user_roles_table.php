<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten UserRole (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('user_email')->nullable();  // Brukerens e-post
            $table->string('role', 64)->nullable();  // Brukerens rolle
            $table->jsonb('permissions')->nullable();  // Spesifikke tilganger
            $table->jsonb('property_access')->nullable();  // Tilgang til spesifikke eiendommer (tom = alle)
            $table->string('status', 64)->default('ventende')->nullable();
            $table->text('invited_by')->nullable();  // Hvem som inviterte brukeren
            $table->date('invited_date')->nullable();  // Dato for invitasjon
            $table->text('notes')->nullable();  // Notater om brukeren
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
