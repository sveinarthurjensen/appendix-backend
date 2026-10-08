<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten UserInviteEvent (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $invite_id
 * @property mixed $invite_email
 * @property mixed $event_type
 * @property mixed $actor_name
 * @property mixed $detail
 */
class UserInviteEvent extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'UserInviteEvent';

    protected $table = 'user_invite_events';

    protected $fillable = [
        'invite_id',
        'invite_email',
        'event_type',
        'actor_name',
        'detail',
    ];

    protected $casts = [

    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'event_type' => ['opprettet', 'sendt', 'åpnet', 'akseptert', 'utløpt', 'tilbakekalt'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['invite_id', 'event_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
