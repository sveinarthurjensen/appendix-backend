<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ContractEvent (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $contract_id
 * @property mixed $event_type
 * @property mixed $actor_user_id
 * @property mixed $actor_email
 * @property mixed $actor_role
 * @property mixed $detail
 * @property mixed $previous_status
 * @property mixed $new_status
 * @property mixed $version_number
 */
class ContractEvent extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ContractEvent';

    protected $table = 'contract_events';

    protected $fillable = [
        'contract_id',
        'event_type',
        'actor_user_id',
        'actor_email',
        'actor_role',
        'detail',
        'previous_status',
        'new_status',
        'version_number',
    ];

    protected $casts = [
        'version_number' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'event_type' => ['opprettet', 'redigert', 'sendt', 'signert_leietaker', 'signert_utleier', 'aktivert', 'oppsagt', 'utløpt', 'terminert', 'arkivert', 'signatur_påminnelse'],
        'actor_role' => ['admin', 'leietaker', 'system'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['contract_id', 'event_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
