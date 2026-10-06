<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten NotificationLog (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('template_id')->nullable();  // Mal-ID
            $table->text('recipient_id')->nullable();  // Mottaker (tenant/booking ID)
            $table->text('recipient_email')->nullable();
            $table->text('recipient_phone')->nullable();
            $table->string('channel', 64)->nullable();
            $table->text('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 64)->default('pending')->nullable();
            $table->timestampTz('sent_date')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();  // Ekstra data om varslingen
            $table->index('template_id');
            $table->index('recipient_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
