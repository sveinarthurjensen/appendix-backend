<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten RentalJournal (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $tenant_id
 * @property mixed $contract_id
 * @property mixed $rental_type
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $monthly_rent
 * @property mixed $total_rent
 * @property mixed $deposit_amount
 * @property mixed $deposit_returned
 * @property mixed $deposit_return_date
 * @property mixed $deposit_deductions
 * @property mixed $deposit_deduction_reason
 * @property mixed $finn_ad_code
 * @property mixed $finn_ad_url
 * @property mixed $finn_ad_screenshot
 * @property mixed $contract_document_url
 * @property mixed $move_in_inspection_url
 * @property mixed $move_out_inspection_url
 * @property mixed $notes
 * @property mixed $status
 * @property mixed $termination_reason
 * @property mixed $year
 */
class RentalJournal extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'RentalJournal';

    protected $table = 'rental_journals';

    protected $fillable = [
        'property_id',
        'tenant_id',
        'contract_id',
        'rental_type',
        'start_date',
        'end_date',
        'monthly_rent',
        'total_rent',
        'deposit_amount',
        'deposit_returned',
        'deposit_return_date',
        'deposit_deductions',
        'deposit_deduction_reason',
        'finn_ad_code',
        'finn_ad_url',
        'finn_ad_screenshot',
        'contract_document_url',
        'move_in_inspection_url',
        'move_out_inspection_url',
        'notes',
        'status',
        'termination_reason',
        'year',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'monthly_rent' => 'float',
        'total_rent' => 'float',
        'deposit_amount' => 'float',
        'deposit_returned' => 'boolean',
        'deposit_return_date' => 'date:Y-m-d',
        'deposit_deductions' => 'float',
        'year' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'rental_type' => ['korttid', 'langtid'],
        'status' => ['aktiv', 'avsluttet', 'arkivert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'start_date', 'rental_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
