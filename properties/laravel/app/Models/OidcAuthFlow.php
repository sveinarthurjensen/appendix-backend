<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use App\Models\Concerns\OidcAuthFlowTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten OidcAuthFlow (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $flow_id
 * @property mixed $auth_code
 * @property mixed $state
 * @property mixed $nonce
 * @property mixed $client_id
 * @property mixed $redirect_uri
 * @property mixed $scope
 * @property mixed $response_type
 * @property mixed $code_challenge
 * @property mixed $code_challenge_method
 * @property mixed $signicat_state
 * @property mixed $signicat_nonce
 * @property mixed $invite_token
 * @property mixed $status
 * @property mixed $source
 * @property mixed $user_id
 * @property mixed $email
 * @property mixed $name
 * @property mixed $nin_hash
 * @property mixed $acr
 * @property mixed $amr
 * @property mixed $access_token
 * @property mixed $created_at
 * @property mixed $expires_at
 * @property mixed $completed_at
 * @property mixed $used_at
 * @property mixed $ip
 * @property mixed $error
 */
class OidcAuthFlow extends Model
{
    use Base44Entity, OidcAuthFlowTokens, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'OidcAuthFlow';

    protected $table = 'oidc_auth_flows';

    protected $fillable = [
        'flow_id',
        'auth_code',
        'state',
        'nonce',
        'client_id',
        'redirect_uri',
        'scope',
        'response_type',
        'code_challenge',
        'code_challenge_method',
        'signicat_state',
        'signicat_nonce',
        'invite_token',
        'status',
        'source',
        'user_id',
        'email',
        'name',
        'nin_hash',
        'acr',
        'amr',
        'access_token',
        'created_at',
        'expires_at',
        'completed_at',
        'used_at',
        'ip',
        'error',
    ];

    protected $casts = [
        'amr' => 'array',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'used_at' => 'datetime',
        'access_expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'status' => ['pending', 'signicat_started', 'completed', 'failed', 'expired'],
        'source' => ['signicat', 'qr_preauth', 'invite', ''],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['flow_id', 'status'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
