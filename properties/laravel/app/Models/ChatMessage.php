<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ChatMessage (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $session_id
 * @property mixed $sender_name
 * @property mixed $sender_email
 * @property mixed $sender_phone
 * @property mixed $message
 * @property mixed $direction
 * @property mixed $channel
 * @property mixed $status
 * @property mixed $property_interest
 * @property mixed $admin_note
 */
class ChatMessage extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ChatMessage';

    protected $table = 'chat_messages';

    protected $fillable = [
        'session_id',
        'sender_name',
        'sender_email',
        'sender_phone',
        'message',
        'direction',
        'channel',
        'status',
        'property_interest',
        'admin_note',
    ];

    protected $casts = [

    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'direction' => ['innkommende', 'utgående'],
        'channel' => ['chat', 'email', 'sms'],
        'status' => ['ulest', 'lest', 'besvart', 'arkivert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['session_id', 'message', 'direction', 'channel'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
