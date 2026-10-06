<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Property (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $address
 * @property mixed $airbnb_ical_import_url
 * @property mixed $amenities
 * @property mixed $annual_property_tax
 * @property mixed $bathrooms
 * @property mixed $bedrooms
 * @property mixed $building_insurance_paid_by
 * @property mixed $check_in_time
 * @property mixed $check_out_time
 * @property mixed $city
 * @property mixed $cleaning_fee
 * @property mixed $cleaning_fee_description
 * @property mixed $contents_insurance_paid_by
 * @property mixed $deposit_amount
 * @property mixed $description
 * @property mixed $document_library
 * @property mixed $electricity_paid_by
 * @property mixed $finn_code
 * @property mixed $gnr_bnr
 * @property mixed $high_season_months
 * @property mixed $ical_export_token
 * @property mixed $images
 * @property mixed $latitude
 * @property mixed $loan_amount
 * @property mixed $loan_interest_rate
 * @property mixed $loan_type
 * @property mixed $longitude
 * @property mixed $main_image
 * @property mixed $max_guests
 * @property mixed $min_nights_default
 * @property mixed $municipal_fees_paid_by
 * @property mixed $municipality
 * @property mixed $municipality_number
 * @property mixed $name
 * @property mixed $postal_code
 * @property mixed $price_per_month
 * @property mixed $price_per_night
 * @property mixed $price_per_night_high
 * @property mixed $price_per_night_weekend
 * @property mixed $price_per_night_weekend_high
 * @property mixed $purchase_date
 * @property mixed $purchase_price
 * @property mixed $rental_insurance_paid_by
 * @property mixed $rental_mode
 * @property mixed $rental_type
 * @property mixed $rules
 * @property mixed $size_sqm
 * @property mixed $status
 * @property mixed $tax_classification
 * @property mixed $type
 */
class Property extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Property';

    protected $table = 'properties';

    protected $fillable = [
        'address',
        'airbnb_ical_import_url',
        'amenities',
        'annual_property_tax',
        'bathrooms',
        'bedrooms',
        'building_insurance_paid_by',
        'check_in_time',
        'check_out_time',
        'city',
        'cleaning_fee',
        'cleaning_fee_description',
        'contents_insurance_paid_by',
        'deposit_amount',
        'description',
        'document_library',
        'electricity_paid_by',
        'finn_code',
        'gnr_bnr',
        'high_season_months',
        'ical_export_token',
        'images',
        'latitude',
        'loan_amount',
        'loan_interest_rate',
        'loan_type',
        'longitude',
        'main_image',
        'max_guests',
        'min_nights_default',
        'municipal_fees_paid_by',
        'municipality',
        'municipality_number',
        'name',
        'postal_code',
        'price_per_month',
        'price_per_night',
        'price_per_night_high',
        'price_per_night_weekend',
        'price_per_night_weekend_high',
        'purchase_date',
        'purchase_price',
        'rental_insurance_paid_by',
        'rental_mode',
        'rental_type',
        'rules',
        'size_sqm',
        'status',
        'tax_classification',
        'type',
    ];

    protected $casts = [
        'amenities' => 'array',
        'annual_property_tax' => 'float',
        'bathrooms' => 'float',
        'bedrooms' => 'float',
        'cleaning_fee' => 'float',
        'deposit_amount' => 'float',
        'document_library' => 'array',
        'high_season_months' => 'array',
        'images' => 'array',
        'latitude' => 'float',
        'loan_amount' => 'float',
        'loan_interest_rate' => 'float',
        'longitude' => 'float',
        'max_guests' => 'float',
        'min_nights_default' => 'float',
        'price_per_month' => 'float',
        'price_per_night' => 'float',
        'price_per_night_high' => 'float',
        'price_per_night_weekend' => 'float',
        'price_per_night_weekend_high' => 'float',
        'purchase_date' => 'date:Y-m-d',
        'purchase_price' => 'float',
        'size_sqm' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'building_insurance_paid_by' => ['utleier', 'leietaker'],
        'contents_insurance_paid_by' => ['utleier', 'leietaker'],
        'electricity_paid_by' => ['utleier', 'leietaker'],
        'loan_type' => ['flytende', 'fast'],
        'municipal_fees_paid_by' => ['utleier', 'leietaker'],
        'rental_insurance_paid_by' => ['utleier', 'leietaker'],
        'rental_mode' => ['korttid', 'langtid'],
        'rental_type' => ['privat', 'firma'],
        'status' => ['aktiv', 'inaktiv', 'vedlikehold'],
        'tax_classification' => ['primærbolig', 'sekundærbolig', 'næringseiendom'],
        'type' => ['hytte', 'leilighet', 'hus', 'rom'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'type', 'rental_mode', 'address'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
