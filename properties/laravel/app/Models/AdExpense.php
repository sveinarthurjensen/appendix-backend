<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten AdExpense (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $campaign_id
 * @property mixed $google_account_id
 * @property mixed $date
 * @property mixed $amount
 * @property mixed $currency
 * @property mixed $platform
 * @property mixed $payment_card_last_four
 * @property mixed $receipt_url
 * @property mixed $invoice_number
 * @property mixed $impressions
 * @property mixed $clicks
 * @property mixed $conversions
 * @property mixed $notes
 */
class AdExpense extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'AdExpense';

    protected $table = 'ad_expenses';

    protected $fillable = [
        'campaign_id',
        'google_account_id',
        'date',
        'amount',
        'currency',
        'platform',
        'payment_card_last_four',
        'receipt_url',
        'invoice_number',
        'impressions',
        'clicks',
        'conversions',
        'notes',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'amount' => 'float',
        'impressions' => 'float',
        'clicks' => 'float',
        'conversions' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'platform' => ['google', 'finn', 'facebook', 'instagram', 'other'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['date', 'amount', 'platform'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
