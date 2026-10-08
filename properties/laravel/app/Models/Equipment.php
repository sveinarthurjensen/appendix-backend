<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Equipment (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $description
 * @property mixed $category
 * @property mixed $equipment_class
 * @property mixed $property_id
 * @property mixed $location_type
 * @property mixed $room
 * @property mixed $ownership_type
 * @property mixed $tenant_id
 * @property mixed $serial_number
 * @property mixed $model
 * @property mixed $manufacturer
 * @property mixed $purchase_date
 * @property mixed $purchase_price
 * @property mixed $depreciation_years
 * @property mixed $current_value
 * @property mixed $warranty_expires
 * @property mixed $service_interval_months
 * @property mixed $last_service_date
 * @property mixed $next_service_date
 * @property mixed $service_provider_id
 * @property mixed $status
 * @property mixed $documents
 * @property mixed $images
 * @property mixed $notes
 */
class Equipment extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Equipment';

    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'description',
        'category',
        'equipment_class',
        'property_id',
        'location_type',
        'room',
        'ownership_type',
        'tenant_id',
        'serial_number',
        'model',
        'manufacturer',
        'purchase_date',
        'purchase_price',
        'depreciation_years',
        'current_value',
        'warranty_expires',
        'service_interval_months',
        'last_service_date',
        'next_service_date',
        'service_provider_id',
        'status',
        'documents',
        'images',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'purchase_price' => 'float',
        'depreciation_years' => 'float',
        'current_value' => 'float',
        'warranty_expires' => 'date:Y-m-d',
        'service_interval_months' => 'float',
        'last_service_date' => 'date:Y-m-d',
        'next_service_date' => 'date:Y-m-d',
        'documents' => 'array',
        'images' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'category' => ['medisinsk_teknisk', 'hvitevarer', 'møbler', 'elektronikk', 'verktøy', 'sikkerhetsutstyr', 'vvs', 'elektrisk', 'brannsikring', 'utvendig', 'annet'],
        'equipment_class' => ['klasse_1', 'klasse_2', 'klasse_3', 'ikke_klassifisert'],
        'location_type' => ['innvendig', 'utvendig', 'fellesareal', 'bod', 'garasje', 'operasjonsstue', 'kontor', 'annet'],
        'ownership_type' => ['eiet', 'utleid_til_leietaker', 'leaset', 'lånt'],
        'status' => ['aktiv', 'til_service', 'reparasjon', 'kassert', 'solgt'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'category', 'property_id'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
