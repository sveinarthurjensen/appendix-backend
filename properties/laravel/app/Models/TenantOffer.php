<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten TenantOffer (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $tenant_id
 * @property mixed $property_id
 * @property mixed $title
 * @property mixed $description
 * @property mixed $offer_type
 * @property mixed $discount_percent
 * @property mixed $discount_amount
 * @property mixed $valid_from
 * @property mixed $valid_until
 * @property mixed $status
 * @property mixed $terms
 * @property mixed $accepted_date
 */
class TenantOffer extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'TenantOffer';

    protected $table = 'tenant_offers';

    protected $fillable = [
        'tenant_id',
        'property_id',
        'title',
        'description',
        'offer_type',
        'discount_percent',
        'discount_amount',
        'valid_from',
        'valid_until',
        'status',
        'terms',
        'accepted_date',
    ];

    protected $casts = [
        'discount_percent' => 'float',
        'discount_amount' => 'float',
        'valid_from' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'accepted_date' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'offer_type' => ['discount', 'upgrade', 'service', 'loyalty', 'other'],
        'status' => ['pending', 'accepted', 'declined', 'expired'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['tenant_id', 'title', 'description'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
