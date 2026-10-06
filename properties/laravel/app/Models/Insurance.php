<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Insurance (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $insurance_type
 * @property mixed $rental_category
 * @property mixed $provider
 * @property mixed $policy_number
 * @property mixed $coverage_amount
 * @property mixed $deductible
 * @property mixed $annual_premium
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $auto_renew
 * @property mixed $contact_person
 * @property mixed $contact_phone
 * @property mixed $contact_email
 * @property mixed $document_url
 * @property mixed $notes
 * @property mixed $status
 */
class Insurance extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Insurance';

    protected $table = 'insurances';

    protected $fillable = [
        'property_id',
        'insurance_type',
        'rental_category',
        'provider',
        'policy_number',
        'coverage_amount',
        'deductible',
        'annual_premium',
        'start_date',
        'end_date',
        'auto_renew',
        'contact_person',
        'contact_phone',
        'contact_email',
        'document_url',
        'notes',
        'status',
    ];

    protected $casts = [
        'coverage_amount' => 'float',
        'deductible' => 'float',
        'annual_premium' => 'float',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'auto_renew' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'insurance_type' => ['bygningsforsikring', 'innboforsikring', 'huseieransvar', 'utleieforsikring', 'naturskadeforsikring', 'vannskade', 'brannforsikring', 'tyveri', 'rettshjelpforsikring', 'driftsavbrudd', 'næringsforsikring', 'ansvarsforsikring_bedrift', 'annet'],
        'rental_category' => ['privat', 'firma', 'begge'],
        'status' => ['aktiv', 'utløpt', 'kansellert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'insurance_type', 'provider'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
