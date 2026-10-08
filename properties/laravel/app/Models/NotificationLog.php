<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten NotificationLog (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $template_id
 * @property mixed $recipient_id
 * @property mixed $recipient_email
 * @property mixed $recipient_phone
 * @property mixed $channel
 * @property mixed $subject
 * @property mixed $body
 * @property mixed $status
 * @property mixed $sent_date
 * @property mixed $error_message
 * @property mixed $metadata
 */
class NotificationLog extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'NotificationLog';

    protected $table = 'notification_logs';

    protected $fillable = [
        'template_id',
        'recipient_id',
        'recipient_email',
        'recipient_phone',
        'channel',
        'subject',
        'body',
        'status',
        'sent_date',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'sent_date' => 'datetime',
        'metadata' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'channel' => ['email', 'sms'],
        'status' => ['sent', 'failed', 'pending'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['recipient_email', 'channel', 'body'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
