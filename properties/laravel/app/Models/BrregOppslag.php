<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten BrregOppslag (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $entity_name
 * @property mixed $record_id
 * @property mixed $org_number
 * @property mixed $er_gjeldende
 * @property mixed $navn
 * @property mixed $organisasjonsform
 * @property mixed $organisasjonsform_kode
 * @property mixed $forretningsadresse
 * @property mixed $postnummer
 * @property mixed $poststed
 * @property mixed $land
 * @property mixed $hjemmeside
 * @property mixed $epostadresse
 * @property mixed $telefon
 * @property mixed $naeringskode
 * @property mixed $naeringsbeskrivelse
 * @property mixed $antall_ansatte
 * @property mixed $stiftelsesdato
 * @property mixed $registreringsdato
 * @property mixed $registrert_i_foretaksregisteret
 * @property mixed $registrert_i_mvaregisteret
 * @property mixed $under_avvikling
 * @property mixed $under_tvangsavvikling
 * @property mixed $konkurs
 * @property mixed $styre
 * @property mixed $styre_sist_endret
 * @property mixed $daglig_leder
 * @property mixed $signatur
 * @property mixed $prokura
 * @property mixed $revisor
 * @property mixed $regnskapsforer
 * @property mixed $signaturmerknad
 * @property mixed $status_varsler
 * @property mixed $oppslag_at
 * @property mixed $oppslag_av
 */
class BrregOppslag extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'BrregOppslag';

    protected $table = 'brreg_oppslags';

    protected $fillable = [
        'entity_name',
        'record_id',
        'org_number',
        'er_gjeldende',
        'navn',
        'organisasjonsform',
        'organisasjonsform_kode',
        'forretningsadresse',
        'postnummer',
        'poststed',
        'land',
        'hjemmeside',
        'epostadresse',
        'telefon',
        'naeringskode',
        'naeringsbeskrivelse',
        'antall_ansatte',
        'stiftelsesdato',
        'registreringsdato',
        'registrert_i_foretaksregisteret',
        'registrert_i_mvaregisteret',
        'under_avvikling',
        'under_tvangsavvikling',
        'konkurs',
        'styre',
        'styre_sist_endret',
        'daglig_leder',
        'signatur',
        'prokura',
        'revisor',
        'regnskapsforer',
        'signaturmerknad',
        'status_varsler',
        'oppslag_at',
        'oppslag_av',
    ];

    protected $casts = [
        'er_gjeldende' => 'boolean',
        'antall_ansatte' => 'float',
        'registrert_i_foretaksregisteret' => 'boolean',
        'registrert_i_mvaregisteret' => 'boolean',
        'under_avvikling' => 'boolean',
        'under_tvangsavvikling' => 'boolean',
        'konkurs' => 'boolean',
        'styre' => 'array',
        'daglig_leder' => 'array',
        'signatur' => 'array',
        'prokura' => 'array',
        'revisor' => 'array',
        'regnskapsforer' => 'array',
        'status_varsler' => 'array',
        'oppslag_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['entity_name', 'record_id', 'org_number'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
