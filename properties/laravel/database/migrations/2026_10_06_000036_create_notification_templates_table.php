<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten NotificationTemplate (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('name')->nullable();  // Navn på mal
            $table->string('type', 64)->nullable();  // Type varsel
            $table->string('channel', 64)->default('email')->nullable();
            $table->text('subject')->nullable();  // E-postemne
            $table->text('email_body')->nullable();  // E-postinnhold (støtter variabler: {{name}}, {{property}}, {{amount}}, {{date}})
            $table->text('sms_body')->nullable();  // SMS-innhold (maks 160 tegn)
            $table->string('trigger', 64)->default('manual')->nullable();
            $table->decimal('days_before', 18, 4)->nullable();  // Dager før hendelse (for påminnelser)
            $table->string('target_segment', 64)->nullable();  // Målgruppe
            $table->boolean('is_active')->default(true)->nullable();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
