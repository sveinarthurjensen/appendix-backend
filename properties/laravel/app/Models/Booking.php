<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Booking (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $tenant_id
 * @property mixed $booking_type
 * @property mixed $booking_source
 * @property mixed $external_booking_id
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $guests
 * @property mixed $total_price
 * @property mixed $deposit_paid
 * @property mixed $status
 * @property mixed $payment_status
 * @property mixed $special_requests
 * @property mixed $check_in_time
 * @property mixed $check_out_time
 * @property mixed $contract_id
 */
class Booking extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Booking';

    protected $table = 'bookings';

    protected $fillable = [
        'property_id',
        'tenant_id',
        'booking_type',
        'booking_source',
        'external_booking_id',
        'start_date',
        'end_date',
        'guests',
        'total_price',
        'deposit_paid',
        'status',
        'payment_status',
        'special_requests',
        'check_in_time',
        'check_out_time',
        'contract_id',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'guests' => 'float',
        'total_price' => 'float',
        'deposit_paid' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'booking_type' => ['korttid', 'langtid'],
        'booking_source' => ['direkte', 'airbnb', 'booking.com', 'hotels.com', 'expedia', 'annet'],
        'status' => ['forespørsel', 'bekreftet', 'innsjekket', 'fullført', 'kansellert'],
        'payment_status' => ['venter', 'delvis_betalt', 'betalt', 'refundert', 'ikke_relevant'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'tenant_id', 'start_date', 'end_date', 'booking_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = ['tenant_id' => 'id'];
}
