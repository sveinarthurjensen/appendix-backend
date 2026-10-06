<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Tenant (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $first_name
 * @property mixed $last_name
 * @property mixed $email
 * @property mixed $phone
 * @property mixed $address
 * @property mixed $city
 * @property mixed $postal_code
 * @property mixed $country
 * @property mixed $id_number
 * @property mixed $id_document_url
 * @property mixed $notes
 * @property mixed $status
 */
class Tenant extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Tenant';

    protected $table = 'tenants';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'id_number',
        'id_document_url',
        'notes',
        'status',
    ];

    protected $casts = [

    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'status' => ['aktiv', 'tidligere', 'blokkert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['first_name', 'last_name', 'email', 'phone'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = ['email' => 'email'];
}
