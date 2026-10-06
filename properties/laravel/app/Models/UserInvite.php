<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten UserInvite (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $token
 * @property mixed $short_code
 * @property mixed $target_user_id
 * @property mixed $name
 * @property mixed $email
 * @property mixed $phone
 * @property mixed $role
 * @property mixed $nin_hash
 * @property mixed $status
 * @property mixed $expires_at
 * @property mixed $used_at
 * @property mixed $used_flow_id
 * @property mixed $created_by_id
 * @property mixed $created_by_name
 * @property mixed $created_at
 * @property mixed $approved_at
 * @property mixed $approved_by_id
 * @property mixed $email_subject
 * @property mixed $email_body
 * @property mixed $sms_body
 * @property mixed $registration_sent_at
 * @property mixed $sms_sent_at
 * @property mixed $welcome_email_sent_at
 */
class UserInvite extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'UserInvite';

    protected $table = 'user_invites';

    protected $fillable = [
        'token',
        'short_code',
        'target_user_id',
        'name',
        'email',
        'phone',
        'role',
        'nin_hash',
        'status',
        'expires_at',
        'used_at',
        'used_flow_id',
        'created_by_id',
        'created_by_name',
        'created_at',
        'approved_at',
        'approved_by_id',
        'email_subject',
        'email_body',
        'sms_body',
        'registration_sent_at',
        'sms_sent_at',
        'welcome_email_sent_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
        'approved_at' => 'datetime',
        'registration_sent_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'welcome_email_sent_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'role' => ['admin', 'arbeider', 'leietaker', 'guest'],
        'status' => ['draft', 'sent', 'used', 'expired', 'cancelled'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['token', 'status'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
