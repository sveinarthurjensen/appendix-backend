<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten TenantPayment (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $tenant_id
 * @property mixed $property_id
 * @property mixed $contract_id
 * @property mixed $amount
 * @property mixed $type
 * @property mixed $period
 * @property mixed $due_date
 * @property mixed $paid_date
 * @property mixed $status
 * @property mixed $payment_reference
 * @property mixed $receipt_url
 * @property mixed $notes
 */
class TenantPayment extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'TenantPayment';

    protected $table = 'tenant_payments';

    protected $fillable = [
        'tenant_id',
        'property_id',
        'contract_id',
        'amount',
        'type',
        'period',
        'due_date',
        'paid_date',
        'status',
        'payment_reference',
        'receipt_url',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'due_date' => 'date:Y-m-d',
        'paid_date' => 'date:Y-m-d',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'type' => ['husleie', 'depositum', 'strøm', 'internett', 'annet'],
        'status' => ['venter', 'betalt', 'forfalt', 'kansellert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['tenant_id', 'property_id', 'amount', 'type', 'due_date'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
