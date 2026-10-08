<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Location (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $source_app
 * @property mixed $company_id
 * @property mixed $external_id
 * @property mixed $name
 * @property mixed $type
 * @property mixed $street
 * @property mixed $postal_code
 * @property mixed $city
 * @property mixed $area_sqm
 * @property mixed $status
 * @property mixed $description
 * @property mixed $latitude
 * @property mixed $longitude
 * @property mixed $last_sync
 * @property mixed $sync_metadata
 */
class Location extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Location';

    protected $table = 'locations';

    protected $fillable = [
        'source_app',
        'company_id',
        'external_id',
        'name',
        'type',
        'street',
        'postal_code',
        'city',
        'area_sqm',
        'status',
        'description',
        'latitude',
        'longitude',
        'last_sync',
        'sync_metadata',
    ];

    protected $casts = [
        'area_sqm' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'last_sync' => 'datetime',
        'sync_metadata' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'type' => ['eiendom', 'bygning', 'etasje', 'rom', 'leilighet', 'annet'],
        'status' => ['aktiv', 'inaktiv', 'planlagt', 'utfaset'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
