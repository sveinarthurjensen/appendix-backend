<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Generert fra Base44-entiteten Property (appendix_properties). Ikke rediger for hånd – endre generatoren. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            // Standardkolonner (Base44-kompatible)
            $table->string('id', 32)->primary();          // Base44-ID beholdes ved import, ulid på nye
            $table->string('app_id', 64)->index();
            $table->string('created_by', 255)->nullable()->index();   // e-post, som i Base44
            $table->timestampTz('created_date')->nullable()->index();
            $table->timestampTz('updated_date')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->softDeletesTz();

            // Felt fra skjemaet
            $table->text('address')->nullable();  // Adresse
            $table->text('airbnb_ical_import_url')->nullable();  // Airbnb/Booking iCal-URL for import av opptatte datoer
            $table->jsonb('amenities')->nullable();  // Fasiliteter
            $table->decimal('annual_property_tax', 18, 4)->nullable();  // Årlig eiendomsskatt
            $table->decimal('bathrooms', 18, 4)->nullable();  // Antall bad
            $table->decimal('bedrooms', 18, 4)->nullable();  // Antall soverom
            $table->string('building_insurance_paid_by', 64)->default('utleier')->nullable();  // Hvem betaler bygningsforsikring
            $table->text('check_in_time')->nullable();  // Innsjekk tidspunkt
            $table->text('check_out_time')->nullable();  // Utsjekk tidspunkt
            $table->text('city')->nullable();  // By/sted
            $table->decimal('cleaning_fee', 18, 4)->nullable();  // Tilretteleggingsgebyr/vaskekostnad
            $table->text('cleaning_fee_description')->nullable();  // Beskrivelse av tilretteleggingsgebyr
            $table->string('contents_insurance_paid_by', 64)->default('leietaker')->nullable();  // Hvem betaler innboforsikring
            $table->decimal('deposit_amount', 18, 4)->nullable();  // Depositum
            $table->text('description')->nullable();  // Beskrivelse av eiendommen
            $table->jsonb('document_library')->nullable();  // Dokumentbibliotek for eiendommen
            $table->string('electricity_paid_by', 64)->default('leietaker')->nullable();  // Hvem betaler strømutgifter
            $table->text('finn_code')->nullable();  // FINN.no kode
            $table->text('gnr_bnr')->nullable();  // Gårdsnummer/bruksnummer
            $table->jsonb('high_season_months')->nullable();  // Måneder (1-12) som regnes som høysesong, f.eks. [12,1,2,3,4] for skisesong
            $table->text('ical_export_token')->nullable();  // Engangs-token som beskytter eiendommens offentlige iCal-eksportlenke
            $table->jsonb('images')->nullable();  // Bilder av eiendommen
            $table->decimal('latitude', 18, 4)->nullable();  // Breddegrad
            $table->decimal('loan_amount', 18, 4)->nullable();  // Gjenværende lån på eiendommen
            $table->decimal('loan_interest_rate', 18, 4)->nullable();  // Lånerente (prosent)
            $table->string('loan_type', 64)->default('flytende')->nullable();  // Type lån
            $table->decimal('longitude', 18, 4)->nullable();  // Lengdegrad
            $table->text('main_image')->nullable();  // Hovedbilde
            $table->decimal('max_guests', 18, 4)->nullable();  // Maks antall gjester (korttid)
            $table->decimal('min_nights_default', 18, 4)->nullable();  // Standard minimum antall netter (korttid) når datoen ikke har eget krav
            $table->string('municipal_fees_paid_by', 64)->default('utleier')->nullable();  // Hvem betaler kommunale avgifter
            $table->text('municipality')->nullable();  // Kommune
            $table->text('municipality_number')->nullable();  // Kommunenummer
            $table->text('name')->nullable();  // Navn på eiendommen
            $table->text('postal_code')->nullable();  // Postnummer
            $table->decimal('price_per_month', 18, 4)->nullable();  // Pris per måned (langtid)
            $table->decimal('price_per_night', 18, 4)->nullable();  // Pris per natt ukedag (korttid)
            $table->decimal('price_per_night_high', 18, 4)->nullable();  // Pris per natt hverdag i høysesong (korttid). Tom = bruk price_per_night
            $table->decimal('price_per_night_weekend', 18, 4)->nullable();  // Pris per natt helg (korttid)
            $table->decimal('price_per_night_weekend_high', 18, 4)->nullable();  // Pris per natt helg (fre/lør) i høysesong (korttid). Tom = bruk price_per_night_weekend
            $table->date('purchase_date')->nullable();  // Kjøpsdato
            $table->decimal('purchase_price', 18, 4)->nullable();  // Kjøpspris
            $table->string('rental_insurance_paid_by', 64)->default('utleier')->nullable();  // Hvem betaler utleieforsikring
            $table->string('rental_mode', 64)->nullable();  // Utleiemodus - korttid eller langtid
            $table->string('rental_type', 64)->default('privat')->nullable();  // Type utleie - privat eller firma
            $table->text('rules')->nullable();  // Husregler
            $table->decimal('size_sqm', 18, 4)->nullable();  // Størrelse i kvadratmeter
            $table->string('status', 64)->default('aktiv')->nullable();
            $table->string('tax_classification', 64)->default('sekundærbolig')->nullable();  // Skattemessig klassifisering
            $table->string('type', 64)->nullable();  // Type eiendom
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
