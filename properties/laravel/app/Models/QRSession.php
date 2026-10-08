<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten QRSession (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $session_id
 * @property mixed $status
 * @property mixed $purpose
 * @property mixed $document_type
 * @property mixed $document_id
 * @property mixed $signer_role
 * @property mixed $payload_hash
 * @property mixed $created_at
 * @property mixed $expires_at
 * @property mixed $approved_user_id
 * @property mixed $approved_at
 * @property mixed $approved_token
 * @property mixed $consumed_at
 * @property mixed $mobile_user_id
 * @property mixed $bridge_preauth_token
 * @property mixed $bridge_used_at
 */
class QRSession extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'QRSession';

    protected $table = 'qr_sessions';

    protected $fillable = [
        'session_id',
        'status',
        'purpose',
        'document_type',
        'document_id',
        'signer_role',
        'payload_hash',
        'created_at',
        'expires_at',
        'approved_user_id',
        'approved_at',
        'approved_token',
        'consumed_at',
        'mobile_user_id',
        'bridge_preauth_token',
        'bridge_used_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'consumed_at' => 'datetime',
        'bridge_used_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'status' => ['pending', 'approved', 'rejected', 'expired', 'consumed'],
        'purpose' => ['login', 'signing', 'stepup'],
        'signer_role' => ['leietaker', 'utleier'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['session_id', 'status'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
