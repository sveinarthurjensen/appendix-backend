<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Engangskoder (SMS/e-post) for innlogging på begrenset nivå. Koden lagres aldri i klartekst. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('channel', 8);                 // sms | email
            $table->string('target_hash', 64)->index();   // HMAC av normalisert mobil/e-post
            $table->string('target_masked', 64)->nullable(); // f.eks. ••• •• 833 – til visning/logg
            $table->string('user_id', 64)->nullable();    // satt bare når mottaker er en kjent, tillatt bruker
            $table->string('code_hash', 64)->nullable();  // HMAC(kode); null når ingen kode ble sendt (ukjent mottaker)
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
