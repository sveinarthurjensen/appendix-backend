<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Income (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $tenant_id
 * @property mixed $journal_id
 * @property mixed $date
 * @property mixed $year
 * @property mixed $category
 * @property mixed $rental_type
 * @property mixed $description
 * @property mixed $amount
 * @property mixed $payment_method
 * @property mixed $is_taxable
 * @property mixed $reference
 * @property mixed $receipt_url
 * @property mixed $notes
 */
class Income extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Income';

    protected $table = 'incomes';

    protected $fillable = [
        'property_id',
        'tenant_id',
        'journal_id',
        'date',
        'year',
        'category',
        'rental_type',
        'description',
        'amount',
        'payment_method',
        'is_taxable',
        'reference',
        'receipt_url',
        'notes',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'year' => 'float',
        'amount' => 'float',
        'is_taxable' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'category' => ['husleie', 'depositum_mottatt', 'depositum_tilbakebetalt', 'strøm_refusjon', 'internett_refusjon', 'skade_erstatning', 'annet'],
        'rental_type' => ['privat', 'firma'],
        'payment_method' => ['bankoverføring', 'vipps', 'kontant'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'date', 'category', 'amount'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
