<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten GoogleAdsAccount (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $account_id
 * @property mixed $entity_type
 * @property mixed $status
 * @property mixed $access_token
 * @property mixed $refresh_token
 * @property mixed $last_sync
 * @property mixed $total_spend
 * @property mixed $payment_cards
 * @property mixed $notes
 */
class GoogleAdsAccount extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'GoogleAdsAccount';

    protected $table = 'google_ads_accounts';

    protected $fillable = [
        'name',
        'account_id',
        'entity_type',
        'status',
        'access_token',
        'refresh_token',
        'last_sync',
        'total_spend',
        'payment_cards',
        'notes',
    ];

    protected $casts = [
        'last_sync' => 'datetime',
        'total_spend' => 'float',
        'payment_cards' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'entity_type' => ['company', 'clinic', 'hospital', 'department'],
        'status' => ['active', 'inactive', 'pending'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'entity_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
