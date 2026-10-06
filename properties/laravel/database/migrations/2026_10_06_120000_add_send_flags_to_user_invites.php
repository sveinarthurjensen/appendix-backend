<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Felt createUserInvite bruker, men som ikke lå i Base44-skjemaet. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_invites', function (Blueprint $table) {
            $table->boolean('send_email')->nullable();
            $table->boolean('send_sms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_invites', fn (Blueprint $t) => $t->dropColumn(['send_email', 'send_sms']));
    }
};
