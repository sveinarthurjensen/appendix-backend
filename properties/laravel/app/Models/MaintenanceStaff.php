<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten MaintenanceStaff (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $company
 * @property mixed $org_number
 * @property mixed $email
 * @property mixed $phone
 * @property mixed $address
 * @property mixed $specialization
 * @property mixed $hourly_rate
 * @property mixed $contract_url
 * @property mixed $notes
 * @property mixed $status
 * @property mixed $assigned_properties
 */
class MaintenanceStaff extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'MaintenanceStaff';

    protected $table = 'maintenance_staffs';

    protected $fillable = [
        'name',
        'company',
        'org_number',
        'email',
        'phone',
        'address',
        'specialization',
        'hourly_rate',
        'contract_url',
        'notes',
        'status',
        'assigned_properties',
    ];

    protected $casts = [
        'specialization' => 'array',
        'hourly_rate' => 'float',
        'assigned_properties' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'status' => ['aktiv', 'inaktiv'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'email', 'phone'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
