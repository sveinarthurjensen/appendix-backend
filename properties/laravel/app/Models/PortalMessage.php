<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten PortalMessage (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $case_id
 * @property mixed $case_number
 * @property mixed $subject
 * @property mixed $user_email
 * @property mixed $user_id
 * @property mixed $direction
 * @property mixed $message
 * @property mixed $channel
 * @property mixed $sender_name
 * @property mixed $status
 */
class PortalMessage extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'PortalMessage';

    protected $table = 'portal_messages';

    protected $fillable = [
        'case_id',
        'case_number',
        'subject',
        'user_email',
        'user_id',
        'direction',
        'message',
        'channel',
        'sender_name',
        'status',
    ];

    protected $casts = [

    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'direction' => ['innkommende', 'utgående'],
        'channel' => ['nettside', 'saksbehandling', 'minside'],
        'status' => ['ulest', 'lest'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['case_id', 'user_email', 'direction', 'message', 'channel'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
