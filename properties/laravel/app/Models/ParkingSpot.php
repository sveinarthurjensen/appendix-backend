<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ParkingSpot (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $spot_number
 * @property mixed $property_id
 * @property mixed $location_description
 * @property mixed $latitude
 * @property mixed $longitude
 * @property mixed $spot_type
 * @property mixed $has_charging
 * @property mixed $has_roof
 * @property mixed $has_barrier
 * @property mixed $access_code
 * @property mixed $price_per_hour
 * @property mixed $price_per_day
 * @property mixed $price_per_month
 * @property mixed $payment_link
 * @property mixed $qr_code_url
 * @property mixed $image
 * @property mixed $status
 * @property mixed $notes
 */
class ParkingSpot extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ParkingSpot';

    protected $table = 'parking_spots';

    protected $fillable = [
        'name',
        'spot_number',
        'property_id',
        'location_description',
        'latitude',
        'longitude',
        'spot_type',
        'has_charging',
        'has_roof',
        'has_barrier',
        'access_code',
        'price_per_hour',
        'price_per_day',
        'price_per_month',
        'payment_link',
        'qr_code_url',
        'image',
        'status',
        'notes',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'has_charging' => 'boolean',
        'has_roof' => 'boolean',
        'has_barrier' => 'boolean',
        'price_per_hour' => 'float',
        'price_per_day' => 'float',
        'price_per_month' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'spot_type' => ['utendørs', 'garasje', 'parkeringshus', 'lading', 'annet'],
        'status' => ['ledig', 'utleid', 'reservert', 'vedlikehold'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
