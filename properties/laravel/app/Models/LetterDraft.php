<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten LetterDraft (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $case_id
 * @property mixed $title
 * @property mixed $letter_type
 * @property mixed $template_id
 * @property mixed $recipient_name
 * @property mixed $recipient_address
 * @property mixed $recipient_email
 * @property mixed $sender_name
 * @property mixed $sender_title
 * @property mixed $letter_date
 * @property mixed $subject
 * @property mixed $body
 * @property mixed $signature_user_id
 * @property mixed $signature_name
 * @property mixed $signature_method
 * @property mixed $signed_url
 * @property mixed $signed_date
 * @property mixed $document_url
 * @property mixed $status
 * @property mixed $property_id
 */
class LetterDraft extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'LetterDraft';

    protected $table = 'letter_drafts';

    protected $fillable = [
        'case_id',
        'title',
        'letter_type',
        'template_id',
        'recipient_name',
        'recipient_address',
        'recipient_email',
        'sender_name',
        'sender_title',
        'letter_date',
        'subject',
        'body',
        'signature_user_id',
        'signature_name',
        'signature_method',
        'signed_url',
        'signed_date',
        'document_url',
        'status',
        'property_id',
    ];

    protected $casts = [
        'letter_date' => 'date:Y-m-d',
        'signed_date' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'letter_type' => ['klage', 'tilbud', 'varsel', 'purring', 'kvittering', 'svar', 'generelt', 'annet'],
        'signature_method' => ['elektronisk', 'bankid', 'manual'],
        'status' => ['utkast', 'sendt', 'signert', 'arkivert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['title', 'letter_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
