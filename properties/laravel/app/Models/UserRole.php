<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten UserRole (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $user_email
 * @property mixed $role
 * @property mixed $permissions
 * @property mixed $property_access
 * @property mixed $status
 * @property mixed $invited_by
 * @property mixed $invited_date
 * @property mixed $notes
 */
class UserRole extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'UserRole';

    protected $table = 'user_roles';

    protected $fillable = [
        'user_email',
        'role',
        'permissions',
        'property_access',
        'status',
        'invited_by',
        'invited_date',
        'notes',
    ];

    protected $casts = [
        'permissions' => 'array',
        'property_access' => 'array',
        'invited_date' => 'date:Y-m-d',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'role' => ['admin', 'sekretær', 'styremedlem', 'regnskapsfører', 'revisor', 'cfo', 'driftsleder', 'lesetilgang'],
        'status' => ['aktiv', 'inaktiv', 'ventende'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['user_email', 'role'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
