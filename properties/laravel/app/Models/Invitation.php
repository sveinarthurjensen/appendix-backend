<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Invitation (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $email
 * @property mixed $full_name
 * @property mixed $mobile
 * @property mixed $role
 * @property mixed $niva
 * @property mixed $national_id_hash
 * @property mixed $national_id_last4
 * @property mixed $token
 * @property mixed $status
 * @property mixed $invited_by
 * @property mixed $invited_at
 * @property mixed $sent_at
 * @property mixed $registered_user_id
 * @property mixed $send_email
 * @property mixed $send_sms
 */
class Invitation extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Invitation';

    protected $table = 'invitations';

    protected $fillable = [
        'email',
        'full_name',
        'mobile',
        'role',
        'niva',
        'national_id_hash',
        'national_id_last4',
        'token',
        'status',
        'invited_by',
        'invited_at',
        'sent_at',
        'registered_user_id',
        'send_email',
        'send_sms',
    ];

    protected $casts = [
        'niva' => 'float',
        'invited_at' => 'datetime',
        'sent_at' => 'datetime',
        'send_email' => 'boolean',
        'send_sms' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'role' => ['admin', 'arbeider', 'leietaker'],
        'niva' => ['1', '2'],
        'status' => ['pending', 'sent', 'registered', 'completed', 'expired'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['email', 'token', 'status'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
