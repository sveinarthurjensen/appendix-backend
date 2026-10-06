<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Erstatter Laravel sin standard users-migrasjon (slett 0001_01_01_000000_create_users_table.php). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('app_id', 64)->index();
            $table->string('email')->index();
            $table->string('full_name')->nullable();
            $table->string('role', 32)->default('user');     // admin | user | guest (som i Base44)
            $table->string('password')->nullable();          // null for BankID-/OIDC-brukere
            $table->rememberToken();
            $table->timestampTz('created_date')->nullable();
            $table->timestampTz('updated_date')->nullable();

            // Base44 User-felt (Appendix Properties)
            $table->string('mobile', 32)->nullable();
            $table->string('account_number', 32)->nullable();
            $table->boolean('admin_approved')->default(false);
            $table->boolean('onboarding_completed')->default(false);
            $table->string('national_id_hash', 128)->nullable();
            $table->string('national_id_last4', 4)->nullable();
            $table->timestampTz('national_id_verified_at')->nullable();
            $table->string('national_id_source', 64)->nullable();
            $table->boolean('bankid_verified')->default(false);
            $table->timestampTz('bankid_verified_at')->nullable();
            $table->string('bankid_match_method', 64)->nullable();
            $table->string('nin_hash', 128)->nullable();
            $table->smallInteger('nin_level')->nullable();
            $table->timestampTz('nin_verified_at')->nullable();

            $table->unique(['app_id', 'email']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id', 32)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
