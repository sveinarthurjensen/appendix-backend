<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten MunicipalInfo (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $municipality_name
 * @property mixed $municipality_number
 * @property mixed $waste_collection
 * @property mixed $nearest_school
 * @property mixed $nearest_kindergarten
 * @property mixed $nearest_grocery
 * @property mixed $nearest_fire_station
 * @property mixed $nearest_hospital
 * @property mixed $nearest_pharmacy
 * @property mixed $public_transport
 * @property mixed $recreation
 * @property mixed $emergency_numbers
 * @property mixed $municipal_services
 * @property mixed $last_updated
 * @property mixed $notes
 */
class MunicipalInfo extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'MunicipalInfo';

    protected $table = 'municipal_infos';

    protected $fillable = [
        'property_id',
        'municipality_name',
        'municipality_number',
        'waste_collection',
        'nearest_school',
        'nearest_kindergarten',
        'nearest_grocery',
        'nearest_fire_station',
        'nearest_hospital',
        'nearest_pharmacy',
        'public_transport',
        'recreation',
        'emergency_numbers',
        'municipal_services',
        'last_updated',
        'notes',
    ];

    protected $casts = [
        'waste_collection' => 'array',
        'nearest_school' => 'array',
        'nearest_kindergarten' => 'array',
        'nearest_grocery' => 'array',
        'nearest_fire_station' => 'array',
        'nearest_hospital' => 'array',
        'nearest_pharmacy' => 'array',
        'public_transport' => 'array',
        'recreation' => 'array',
        'emergency_numbers' => 'array',
        'municipal_services' => 'array',
        'last_updated' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
