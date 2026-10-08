<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ContractVersion (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $contract_id
 * @property mixed $version_number
 * @property mixed $terms
 * @property mixed $terms_hash
 * @property mixed $monthly_rent
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $change_summary
 * @property mixed $version_type
 * @property mixed $status
 * @property mixed $signed_by_landlord
 * @property mixed $signed_by_tenant
 * @property mixed $signed_by_landlord_date
 * @property mixed $signed_by_tenant_date
 * @property mixed $created_by_name
 */
class ContractVersion extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ContractVersion';

    protected $table = 'contract_versions';

    protected $fillable = [
        'contract_id',
        'version_number',
        'terms',
        'terms_hash',
        'monthly_rent',
        'start_date',
        'end_date',
        'change_summary',
        'version_type',
        'status',
        'signed_by_landlord',
        'signed_by_tenant',
        'signed_by_landlord_date',
        'signed_by_tenant_date',
        'created_by_name',
    ];

    protected $casts = [
        'version_number' => 'float',
        'monthly_rent' => 'float',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'signed_by_landlord' => 'boolean',
        'signed_by_tenant' => 'boolean',
        'signed_by_landlord_date' => 'datetime',
        'signed_by_tenant_date' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'version_type' => ['opprettet', 'signert_leietaker', 'signert_utleier', 'aktiv', 'endret', 'forhandsvisning'],
        'status' => ['aktiv', 'arkivert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['contract_id', 'version_number'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
