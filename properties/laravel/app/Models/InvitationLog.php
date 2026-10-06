<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten InvitationLog (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $user_role_id
 * @property mixed $user_email
 * @property mixed $sent_via
 * @property mixed $sent_date
 * @property mixed $message_type
 * @property mixed $status
 * @property mixed $error_message
 */
class InvitationLog extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'InvitationLog';

    protected $table = 'invitation_logs';

    protected $fillable = [
        'user_role_id',
        'user_email',
        'sent_via',
        'sent_date',
        'message_type',
        'status',
        'error_message',
    ];

    protected $casts = [
        'sent_date' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'sent_via' => ['email', 'sms'],
        'message_type' => ['invitasjon', 'påminnelse', 'kontrakt_påminnelse'],
        'status' => ['sendt', 'levert', 'åpnet', 'feilet'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['user_email', 'sent_via', 'message_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
