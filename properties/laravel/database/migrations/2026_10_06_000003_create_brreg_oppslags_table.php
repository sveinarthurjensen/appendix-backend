<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten BrregOppslag (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brreg_oppslags', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('entity_name')->nullable();
            $table->text('record_id')->nullable();
            $table->text('org_number')->nullable();
            $table->boolean('er_gjeldende')->default(true)->nullable();
            $table->text('navn')->nullable();
            $table->text('organisasjonsform')->nullable();
            $table->text('organisasjonsform_kode')->nullable();
            $table->text('forretningsadresse')->nullable();
            $table->text('postnummer')->nullable();
            $table->text('poststed')->nullable();
            $table->text('land')->nullable();
            $table->text('hjemmeside')->nullable();
            $table->text('epostadresse')->nullable();
            $table->text('telefon')->nullable();
            $table->text('naeringskode')->nullable();
            $table->text('naeringsbeskrivelse')->nullable();
            $table->decimal('antall_ansatte', 18, 4)->nullable();
            $table->text('stiftelsesdato')->nullable();
            $table->text('registreringsdato')->nullable();
            $table->boolean('registrert_i_foretaksregisteret')->nullable();
            $table->boolean('registrert_i_mvaregisteret')->nullable();
            $table->boolean('under_avvikling')->nullable();
            $table->boolean('under_tvangsavvikling')->nullable();
            $table->boolean('konkurs')->nullable();
            $table->jsonb('styre')->nullable();  // Sittende styre. Bare fodselsaar lagres, aldri full fodselsdato.
            $table->text('styre_sist_endret')->nullable();
            $table->jsonb('daglig_leder')->nullable();
            $table->jsonb('signatur')->nullable();
            $table->jsonb('prokura')->nullable();
            $table->jsonb('revisor')->nullable();
            $table->jsonb('regnskapsforer')->nullable();
            $table->text('signaturmerknad')->nullable();
            $table->jsonb('status_varsler')->nullable();
            $table->timestampTz('oppslag_at')->nullable();
            $table->text('oppslag_av')->nullable();
            $table->index('record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brreg_oppslags');
    }
};
