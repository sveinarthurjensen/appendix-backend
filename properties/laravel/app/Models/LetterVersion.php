<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten LetterVersion (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $letter_draft_id
 * @property mixed $case_id
 * @property mixed $version_number
 * @property mixed $subject
 * @property mixed $body
 * @property mixed $change_summary
 * @property mixed $version_type
 * @property mixed $status
 * @property mixed $is_ai_generated
 * @property mixed $created_by_name
 * @property mixed $parent_version_id
 */
class LetterVersion extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'LetterVersion';

    protected $table = 'letter_versions';

    protected $fillable = [
        'letter_draft_id',
        'case_id',
        'version_number',
        'subject',
        'body',
        'change_summary',
        'version_type',
        'status',
        'is_ai_generated',
        'created_by_name',
        'parent_version_id',
    ];

    protected $casts = [
        'version_number' => 'float',
        'is_ai_generated' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'version_type' => ['utkast', 'godkjent', 'endringsforslag', 'ai_generert'],
        'status' => ['aktiv', 'arkivert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['letter_draft_id', 'version_number'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
