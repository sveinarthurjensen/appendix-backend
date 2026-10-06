<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten MarketingCampaign (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $type
 * @property mixed $status
 * @property mixed $target_audience
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $budget
 * @property mixed $spent
 * @property mixed $impressions
 * @property mixed $clicks
 * @property mixed $conversions
 * @property mixed $external_id
 * @property mixed $property_id
 * @property mixed $notes
 */
class MarketingCampaign extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'MarketingCampaign';

    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'name',
        'type',
        'status',
        'target_audience',
        'start_date',
        'end_date',
        'budget',
        'spent',
        'impressions',
        'clicks',
        'conversions',
        'external_id',
        'property_id',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'budget' => 'float',
        'spent' => 'float',
        'impressions' => 'float',
        'clicks' => 'float',
        'conversions' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'type' => ['google_ads', 'finn', 'facebook', 'instagram', 'email', 'sms', 'print', 'other'],
        'status' => ['draft', 'active', 'paused', 'completed'],
        'target_audience' => ['patients', 'customers', 'both', 'new', 'existing'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
