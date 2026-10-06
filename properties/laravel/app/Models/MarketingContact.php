<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten MarketingContact (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $type
 * @property mixed $first_name
 * @property mixed $last_name
 * @property mixed $email
 * @property mixed $phone
 * @property mixed $source
 * @property mixed $campaign_id
 * @property mixed $tenant_id
 * @property mixed $consent_marketing
 * @property mixed $consent_date
 * @property mixed $tags
 * @property mixed $notes
 * @property mixed $status
 */
class MarketingContact extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'MarketingContact';

    protected $table = 'marketing_contacts';

    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'email',
        'phone',
        'source',
        'campaign_id',
        'tenant_id',
        'consent_marketing',
        'consent_date',
        'tags',
        'notes',
        'status',
    ];

    protected $casts = [
        'consent_marketing' => 'boolean',
        'consent_date' => 'date:Y-m-d',
        'tags' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'type' => ['customer', 'patient', 'lead', 'prospect'],
        'source' => ['google', 'finn', 'website', 'referral', 'walk_in', 'other'],
        'status' => ['active', 'inactive', 'unsubscribed'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['type', 'email'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
