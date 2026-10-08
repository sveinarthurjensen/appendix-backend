<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten FinnAd (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $finn_code
 * @property mixed $title
 * @property mixed $url
 * @property mixed $status
 * @property mixed $views
 * @property mixed $favorites
 * @property mixed $inquiries
 * @property mixed $published_date
 * @property mixed $expires_date
 * @property mixed $price
 * @property mixed $ad_cost
 * @property mixed $last_sync
 */
class FinnAd extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'FinnAd';

    protected $table = 'finn_ads';

    protected $fillable = [
        'property_id',
        'finn_code',
        'title',
        'url',
        'status',
        'views',
        'favorites',
        'inquiries',
        'published_date',
        'expires_date',
        'price',
        'ad_cost',
        'last_sync',
    ];

    protected $casts = [
        'views' => 'float',
        'favorites' => 'float',
        'inquiries' => 'float',
        'published_date' => 'date:Y-m-d',
        'expires_date' => 'date:Y-m-d',
        'price' => 'float',
        'ad_cost' => 'float',
        'last_sync' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'status' => ['active', 'inactive', 'expired', 'sold'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['finn_code'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
