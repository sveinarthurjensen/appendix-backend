<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 (identitet): hvordan brukeren logger inn (= hvem du er) skilles fra brukerposten (= hva du får gjøre).
 *  - identity_provider: entra (faste ansatte) | bankid (konsulenter, pasienter/parter) | password (midlertidig, fjernes)
 *  - entra_oid:         Microsoft Entra object-id – stabil nøkkel for matching (e-post kan endres)
 *  - access_until:      konsulenter: siste dag med tilgang; etter denne avvises innlogging
 *  - last_login_*:      sist innlogget når/hvordan (brukes i brukeradmin og sikkerhetsrevisjon)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identity_provider', 32)->nullable();
            $table->string('entra_oid', 64)->nullable()->index();
            $table->date('access_until')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_provider', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['identity_provider', 'entra_oid', 'access_until', 'last_login_at', 'last_login_provider']);
        });
    }
};
