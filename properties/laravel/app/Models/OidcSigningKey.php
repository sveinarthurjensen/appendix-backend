<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten OidcSigningKey (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $kid
 * @property mixed $private_pem
 * @property mixed $public_jwk
 * @property mixed $active
 * @property mixed $created_at
 */
class OidcSigningKey extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'OidcSigningKey';

    protected $table = 'oidc_signing_keys';

    protected $fillable = [
        'kid',
        'private_pem',
        'public_jwk',
        'active',
        'created_at',
    ];

    protected $casts = [
        'public_jwk' => 'array',
        'active' => 'boolean',
        'created_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['kid', 'private_pem'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
