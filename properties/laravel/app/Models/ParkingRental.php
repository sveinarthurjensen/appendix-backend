<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ParkingRental (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $spot_id
 * @property mixed $rental_type
 * @property mixed $tenant_id
 * @property mixed $tenant_name
 * @property mixed $tenant_email
 * @property mixed $tenant_phone
 * @property mixed $license_plate
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $start_time
 * @property mixed $end_time
 * @property mixed $price
 * @property mixed $payment_link
 * @property mixed $payment_status
 * @property mixed $status
 * @property mixed $notes
 */
class ParkingRental extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ParkingRental';

    protected $table = 'parking_rentals';

    protected $fillable = [
        'spot_id',
        'rental_type',
        'tenant_id',
        'tenant_name',
        'tenant_email',
        'tenant_phone',
        'license_plate',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'price',
        'payment_link',
        'payment_status',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'price' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'rental_type' => ['korttid', 'langtid'],
        'payment_status' => ['venter', 'betalt', 'forfalt'],
        'status' => ['aktiv', 'fullført', 'kansellert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['spot_id', 'rental_type', 'tenant_name', 'start_date'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
