<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten PropertyAvailability (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $date
 * @property mixed $is_available
 * @property mixed $price_per_night
 * @property mixed $min_nights
 * @property mixed $note
 * @property mixed $source
 */
class PropertyAvailability extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'PropertyAvailability';

    protected $table = 'property_availabilities';

    protected $fillable = [
        'property_id',
        'date',
        'is_available',
        'price_per_night',
        'min_nights',
        'note',
        'source',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_available' => 'boolean',
        'price_per_night' => 'float',
        'min_nights' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'source' => ['manual', 'airbnb_import', 'booking'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'date', 'is_available'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
