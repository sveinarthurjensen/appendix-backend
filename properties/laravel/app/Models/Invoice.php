<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Invoice (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $invoice_number
 * @property mixed $property_id
 * @property mixed $tenant_id
 * @property mixed $contract_id
 * @property mixed $invoice_date
 * @property mixed $due_date
 * @property mixed $period_from
 * @property mixed $period_to
 * @property mixed $items
 * @property mixed $subtotal
 * @property mixed $vat_amount
 * @property mixed $total_amount
 * @property mixed $kid_number
 * @property mixed $bank_account
 * @property mixed $status
 * @property mixed $sent_date
 * @property mixed $paid_date
 * @property mixed $paid_amount
 * @property mixed $payment_reference
 * @property mixed $reminder_count
 * @property mixed $last_reminder_date
 * @property mixed $unieconomy_id
 * @property mixed $pdf_url
 * @property mixed $notes
 */
class Invoice extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Invoice';

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'property_id',
        'tenant_id',
        'contract_id',
        'invoice_date',
        'due_date',
        'period_from',
        'period_to',
        'items',
        'subtotal',
        'vat_amount',
        'total_amount',
        'kid_number',
        'bank_account',
        'status',
        'sent_date',
        'paid_date',
        'paid_amount',
        'payment_reference',
        'reminder_count',
        'last_reminder_date',
        'unieconomy_id',
        'pdf_url',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'period_from' => 'date:Y-m-d',
        'period_to' => 'date:Y-m-d',
        'items' => 'array',
        'subtotal' => 'float',
        'vat_amount' => 'float',
        'total_amount' => 'float',
        'sent_date' => 'datetime',
        'paid_date' => 'date:Y-m-d',
        'paid_amount' => 'float',
        'reminder_count' => 'float',
        'last_reminder_date' => 'date:Y-m-d',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'status' => ['utkast', 'sendt', 'betalt', 'forfalt', 'kreditert', 'kansellert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'tenant_id', 'invoice_date', 'due_date', 'total_amount'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
