<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten TaxReport (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $year
 * @property mixed $total_rental_income
 * @property mixed $total_other_income
 * @property mixed $total_maintenance_expenses
 * @property mixed $total_operating_expenses
 * @property mixed $total_other_expenses
 * @property mixed $depreciation
 * @property mixed $net_income
 * @property mixed $days_rented
 * @property mixed $days_personal_use
 * @property mixed $rental_percentage
 * @property mixed $status
 * @property mixed $notes
 * @property mixed $report_url
 */
class TaxReport extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'TaxReport';

    protected $table = 'tax_reports';

    protected $fillable = [
        'property_id',
        'year',
        'total_rental_income',
        'total_other_income',
        'total_maintenance_expenses',
        'total_operating_expenses',
        'total_other_expenses',
        'depreciation',
        'net_income',
        'days_rented',
        'days_personal_use',
        'rental_percentage',
        'status',
        'notes',
        'report_url',
    ];

    protected $casts = [
        'year' => 'float',
        'total_rental_income' => 'float',
        'total_other_income' => 'float',
        'total_maintenance_expenses' => 'float',
        'total_operating_expenses' => 'float',
        'total_other_expenses' => 'float',
        'depreciation' => 'float',
        'net_income' => 'float',
        'days_rented' => 'float',
        'days_personal_use' => 'float',
        'rental_percentage' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'status' => ['utkast', 'ferdig', 'innsendt'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'year'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
