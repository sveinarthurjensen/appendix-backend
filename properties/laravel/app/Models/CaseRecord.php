<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Case (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $title
 * @property mixed $case_number
 * @property mixed $case_type
 * @property mixed $description
 * @property mixed $status
 * @property mixed $priority
 * @property mixed $property_id
 * @property mixed $property_name
 * @property mixed $tenant_id
 * @property mixed $counterpart_name
 * @property mixed $counterpart_email
 * @property mixed $counterpart_address
 * @property mixed $assigned_to_id
 * @property mixed $assigned_to_name
 * @property mixed $due_date
 * @property mixed $resolution
 * @property mixed $tags
 */
class CaseRecord extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Case';

    protected $table = 'cases';

    protected $fillable = [
        'title',
        'case_number',
        'case_type',
        'description',
        'status',
        'priority',
        'property_id',
        'property_name',
        'tenant_id',
        'counterpart_name',
        'counterpart_email',
        'counterpart_address',
        'assigned_to_id',
        'assigned_to_name',
        'due_date',
        'resolution',
        'tags',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'tags' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'case_type' => ['klage', 'tilbud', 'henvendelse', 'krav', 'varsel', 'purring', 'annet'],
        'status' => ['ny', 'under_behandling', 'avventer_svar', 'lukket'],
        'priority' => ['lav', 'normal', 'hoy'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['title', 'case_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
