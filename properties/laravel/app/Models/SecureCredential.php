<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten SecureCredential (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $company_id
 * @property mixed $name
 * @property mixed $category
 * @property mixed $username
 * @property mixed $encrypted_password
 * @property mixed $url
 * @property mixed $two_factor_enabled
 * @property mixed $two_factor_method
 * @property mixed $recovery_codes
 * @property mixed $notes
 * @property mixed $access_level
 * @property mixed $is_sync_token
 */
class SecureCredential extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'SecureCredential';

    protected $table = 'secure_credentials';

    protected $fillable = [
        'company_id',
        'name',
        'category',
        'username',
        'encrypted_password',
        'url',
        'two_factor_enabled',
        'two_factor_method',
        'recovery_codes',
        'notes',
        'access_level',
        'is_sync_token',
    ];

    protected $casts = [
        'two_factor_enabled' => 'boolean',
        'is_sync_token' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'company_id'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
