<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten WebAuthnCredential (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $user_id
 * @property mixed $credential_id
 * @property mixed $public_key
 * @property mixed $counter
 * @property mixed $transports
 * @property mixed $device_label
 * @property mixed $created_at
 */
class WebAuthnCredential extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'WebAuthnCredential';

    protected $table = 'web_authn_credentials';

    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'counter',
        'transports',
        'device_label',
        'created_at',
    ];

    protected $casts = [
        'counter' => 'float',
        'transports' => 'array',
        'created_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['user_id', 'credential_id', 'public_key'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = ['user_id' => 'id'];
}
