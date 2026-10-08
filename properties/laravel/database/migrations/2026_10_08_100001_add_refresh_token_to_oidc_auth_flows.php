<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base44-utstederen støttet bare authorization_code. Laravel-utstederen gir også refresh_token,
 * som lagres på samme flyt-rad (én rad = én innlogging) sammen med egen utløpstid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oidc_auth_flows', function (Blueprint $table) {
            $table->text('refresh_token')->nullable();
            $table->timestampTz('refresh_expires_at')->nullable();
            $table->timestampTz('access_expires_at')->nullable();
            $table->index('auth_code');
            $table->index('access_token');
            $table->index('refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('oidc_auth_flows', function (Blueprint $table) {
            $table->dropIndex(['auth_code']);
            $table->dropIndex(['access_token']);
            $table->dropIndex(['refresh_token']);
            $table->dropColumn(['refresh_token', 'refresh_expires_at', 'access_expires_at']);
        });
    }
};
