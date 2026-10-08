<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten PropertyInfo (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $wifi_name
 * @property mixed $wifi_password
 * @property mixed $door_code
 * @property mixed $key_location
 * @property mixed $parking_info
 * @property mixed $trash_info
 * @property mixed $heating_info
 * @property mixed $appliances_guide
 * @property mixed $emergency_contacts
 * @property mixed $house_rules
 * @property mixed $check_in_instructions
 * @property mixed $check_out_instructions
 * @property mixed $nearby_places
 * @property mixed $documents
 * @property mixed $custom_info
 */
class PropertyInfo extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'PropertyInfo';

    protected $table = 'property_infos';

    protected $fillable = [
        'property_id',
        'wifi_name',
        'wifi_password',
        'door_code',
        'key_location',
        'parking_info',
        'trash_info',
        'heating_info',
        'appliances_guide',
        'emergency_contacts',
        'house_rules',
        'check_in_instructions',
        'check_out_instructions',
        'nearby_places',
        'documents',
        'custom_info',
    ];

    protected $casts = [
        'emergency_contacts' => 'array',
        'nearby_places' => 'array',
        'documents' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
