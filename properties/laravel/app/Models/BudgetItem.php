<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten BudgetItem (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $year
 * @property mixed $category
 * @property mixed $description
 * @property mixed $amount
 * @property mixed $notes
 */
class BudgetItem extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'BudgetItem';

    protected $table = 'budget_items';

    protected $fillable = [
        'property_id',
        'year',
        'category',
        'description',
        'amount',
        'notes',
    ];

    protected $casts = [
        'year' => 'float',
        'amount' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'category' => ['vedlikehold', 'strøm', 'kommunale_avgifter', 'forsikring', 'renhold', 'annet'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'year', 'category', 'amount'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
