<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten InboxMessage (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $message_type
 * @property mixed $direction
 * @property mixed $from_address
 * @property mixed $to_address
 * @property mixed $subject
 * @property mixed $body_text
 * @property mixed $mailbox
 * @property mixed $sent_date
 * @property mixed $sent_by
 * @property mixed $status
 */
class InboxMessage extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'InboxMessage';

    protected $table = 'inbox_messages';

    protected $fillable = [
        'message_type',
        'direction',
        'from_address',
        'to_address',
        'subject',
        'body_text',
        'mailbox',
        'sent_date',
        'sent_by',
        'status',
    ];

    protected $casts = [
        'sent_date' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'message_type' => ['sms', 'email'],
        'direction' => ['incoming', 'outgoing'],
        'status' => ['ny', 'lest'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['message_type', 'direction'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
