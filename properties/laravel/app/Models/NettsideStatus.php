<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten NettsideStatus (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $feil_paa_rad
 * @property mixed $navn
 * @property mixed $nede_siden
 * @property mixed $ok
 * @property mixed $sist_sjekket
 * @property mixed $siste_feil
 * @property mixed $siste_status
 * @property mixed $svartid_ms
 * @property mixed $url
 * @property mixed $varslet_nede
 */
class NettsideStatus extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'NettsideStatus';

    protected $table = 'nettside_status';

    protected $fillable = [
        'feil_paa_rad',
        'navn',
        'nede_siden',
        'ok',
        'sist_sjekket',
        'siste_feil',
        'siste_status',
        'svartid_ms',
        'url',
        'varslet_nede',
    ];

    protected $casts = [
        'feil_paa_rad' => 'float',
        'nede_siden' => 'datetime',
        'ok' => 'boolean',
        'sist_sjekket' => 'datetime',
        'siste_status' => 'float',
        'svartid_ms' => 'float',
        'varslet_nede' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['url'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
