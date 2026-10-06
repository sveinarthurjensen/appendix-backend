<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten CaseDocument (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $case_id
 * @property mixed $name
 * @property mixed $document_type
 * @property mixed $url
 * @property mixed $description
 * @property mixed $content_text
 * @property mixed $source_party
 * @property mixed $uploaded_date
 * @property mixed $is_new
 */
class CaseDocument extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'CaseDocument';

    protected $table = 'case_documents';

    protected $fillable = [
        'case_id',
        'name',
        'document_type',
        'url',
        'description',
        'content_text',
        'source_party',
        'uploaded_date',
        'is_new',
    ];

    protected $casts = [
        'uploaded_date' => 'datetime',
        'is_new' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'document_type' => ['innkommende', 'utgående', 'vedlegg', 'annet'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['case_id', 'name'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
