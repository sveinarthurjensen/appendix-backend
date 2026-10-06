<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten WebAuthnChallenge (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $user_id
 * @property mixed $challenge
 * @property mixed $purpose
 * @property mixed $created_at
 * @property mixed $expires_at
 * @property mixed $used
 */
class WebAuthnChallenge extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'WebAuthnChallenge';

    protected $table = 'web_authn_challenges';

    protected $fillable = [
        'user_id',
        'challenge',
        'purpose',
        'created_at',
        'expires_at',
        'used',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'purpose' => ['registration', 'authentication'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['user_id', 'challenge', 'purpose'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
