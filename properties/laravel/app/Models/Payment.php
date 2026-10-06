<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Payment (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $booking_id
 * @property mixed $contract_id
 * @property mixed $tenant_id
 * @property mixed $property_id
 * @property mixed $amount
 * @property mixed $payment_type
 * @property mixed $payment_method
 * @property mixed $status
 * @property mixed $due_date
 * @property mixed $paid_date
 * @property mixed $invoice_number
 * @property mixed $description
 * @property mixed $receipt_url
 */
class Payment extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Payment';

    protected $table = 'payments';

    protected $fillable = [
        'booking_id',
        'contract_id',
        'tenant_id',
        'property_id',
        'amount',
        'payment_type',
        'payment_method',
        'status',
        'due_date',
        'paid_date',
        'invoice_number',
        'description',
        'receipt_url',
    ];

    protected $casts = [
        'amount' => 'float',
        'due_date' => 'date:Y-m-d',
        'paid_date' => 'date:Y-m-d',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'payment_type' => ['leie', 'depositum', 'tillegg', 'refusjon', 'strøm', 'internett', 'rengjøring', 'annet'],
        'payment_method' => ['bankoverføring', 'vipps', 'kort', 'kontant', 'faktura'],
        'status' => ['venter', 'behandles', 'betalt', 'forfalt', 'kansellert', 'refundert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['amount', 'payment_type', 'status'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
