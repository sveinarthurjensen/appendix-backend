<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Expense (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $date
 * @property mixed $year
 * @property mixed $category
 * @property mixed $description
 * @property mixed $amount
 * @property mixed $vat_amount
 * @property mixed $supplier
 * @property mixed $invoice_number
 * @property mixed $receipt_url
 * @property mixed $payment_method
 * @property mixed $is_deductible
 * @property mixed $tax_category
 * @property mixed $notes
 */
class Expense extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Expense';

    protected $table = 'expenses';

    protected $fillable = [
        'property_id',
        'date',
        'year',
        'category',
        'description',
        'amount',
        'vat_amount',
        'supplier',
        'invoice_number',
        'receipt_url',
        'payment_method',
        'is_deductible',
        'tax_category',
        'notes',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'year' => 'float',
        'amount' => 'float',
        'vat_amount' => 'float',
        'is_deductible' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'category' => ['vedlikehold', 'forsikring', 'kommunale_avgifter', 'strøm', 'internett', 'renhold', 'møbler_inventar', 'reparasjon', 'oppussing', 'felleskostnader', 'eiendomsskatt', 'forretningsfører', 'annonsering', 'reise', 'juridisk', 'regnskap', 'annet'],
        'payment_method' => ['bankoverføring', 'kort', 'vipps', 'kontant', 'efaktura'],
        'tax_category' => ['vedlikehold_fradrag', 'drift_fradrag', 'avskrivning', 'ikke_fradrag'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'date', 'category', 'amount'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
