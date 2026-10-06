<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten Contract (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $booking_id
 * @property mixed $property_id
 * @property mixed $lessor_company_id
 * @property mixed $invoice_bearer_company_id
 * @property mixed $tenant_id
 * @property mixed $contract_type
 * @property mixed $is_office
 * @property mixed $rental_scope
 * @property mixed $part_time_days_per_week
 * @property mixed $part_time_days
 * @property mixed $office_hours_from
 * @property mixed $office_hours_to
 * @property mixed $office_hours_days
 * @property mixed $included_parking_spots
 * @property mixed $extra_parking_per_day
 * @property mixed $meeting_room_access
 * @property mixed $meeting_room_per_hour
 * @property mixed $meeting_room_per_day
 * @property mixed $common_areas
 * @property mixed $shared_costs_monthly
 * @property mixed $shared_costs_description
 * @property mixed $house_rules
 * @property mixed $office_attachments
 * @property mixed $office_company_name
 * @property mixed $office_contact_name
 * @property mixed $office_contact_phone
 * @property mixed $office_contact_email
 * @property mixed $office_company_address
 * @property mixed $office_space_name
 * @property mixed $duration_type
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $notice_period_months
 * @property mixed $auto_renew
 * @property mixed $renewal_period_months
 * @property mixed $termination_notice_date
 * @property mixed $terminated_by
 * @property mixed $termination_date
 * @property mixed $move_out_date
 * @property mixed $monthly_rent
 * @property mixed $deposit_amount
 * @property mixed $terms
 * @property mixed $terms_hash
 * @property mixed $current_version_number
 * @property mixed $document_url
 * @property mixed $status
 * @property mixed $signed_by_tenant_date
 * @property mixed $signed_by_landlord_date
 * @property mixed $tenant_signature_url
 * @property mixed $landlord_signature_url
 * @property mixed $tenant_signature_method
 * @property mixed $landlord_signature_method
 * @property mixed $tenant_bankid_reference
 * @property mixed $landlord_bankid_reference
 * @property mixed $tenant_bankid_identity
 * @property mixed $landlord_bankid_identity
 * @property mixed $signature_verification_method
 * @property mixed $signing_token
 * @property mixed $holding_transfer_status
 * @property mixed $holding_case_number
 * @property mixed $holding_transferred_at
 * @property mixed $holding_transfer_error
 */
class Contract extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'Contract';

    protected $table = 'contracts';

    protected $fillable = [
        'booking_id',
        'property_id',
        'lessor_company_id',
        'invoice_bearer_company_id',
        'tenant_id',
        'contract_type',
        'is_office',
        'rental_scope',
        'part_time_days_per_week',
        'part_time_days',
        'office_hours_from',
        'office_hours_to',
        'office_hours_days',
        'included_parking_spots',
        'extra_parking_per_day',
        'meeting_room_access',
        'meeting_room_per_hour',
        'meeting_room_per_day',
        'common_areas',
        'shared_costs_monthly',
        'shared_costs_description',
        'house_rules',
        'office_attachments',
        'office_company_name',
        'office_contact_name',
        'office_contact_phone',
        'office_contact_email',
        'office_company_address',
        'office_space_name',
        'duration_type',
        'start_date',
        'end_date',
        'notice_period_months',
        'auto_renew',
        'renewal_period_months',
        'termination_notice_date',
        'terminated_by',
        'termination_date',
        'move_out_date',
        'monthly_rent',
        'deposit_amount',
        'terms',
        'terms_hash',
        'current_version_number',
        'document_url',
        'status',
        'signed_by_tenant_date',
        'signed_by_landlord_date',
        'tenant_signature_url',
        'landlord_signature_url',
        'tenant_signature_method',
        'landlord_signature_method',
        'tenant_bankid_reference',
        'landlord_bankid_reference',
        'tenant_bankid_identity',
        'landlord_bankid_identity',
        'signature_verification_method',
        'signing_token',
        'holding_transfer_status',
        'holding_case_number',
        'holding_transferred_at',
        'holding_transfer_error',
    ];

    protected $casts = [
        'is_office' => 'boolean',
        'part_time_days_per_week' => 'float',
        'included_parking_spots' => 'float',
        'extra_parking_per_day' => 'float',
        'meeting_room_access' => 'boolean',
        'meeting_room_per_hour' => 'float',
        'meeting_room_per_day' => 'float',
        'common_areas' => 'array',
        'shared_costs_monthly' => 'float',
        'office_attachments' => 'array',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'notice_period_months' => 'float',
        'auto_renew' => 'boolean',
        'renewal_period_months' => 'float',
        'termination_notice_date' => 'date:Y-m-d',
        'termination_date' => 'date:Y-m-d',
        'move_out_date' => 'date:Y-m-d',
        'monthly_rent' => 'float',
        'deposit_amount' => 'float',
        'current_version_number' => 'float',
        'signed_by_tenant_date' => 'datetime',
        'signed_by_landlord_date' => 'datetime',
        'holding_transferred_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'contract_type' => ['korttid', 'langtid'],
        'rental_scope' => ['heltid', 'deltid'],
        'duration_type' => ['tidsbestemt', 'løpende'],
        'terminated_by' => ['utleier', 'leietaker'],
        'status' => ['utkast', 'sendt', 'signert_leietaker', 'signert_utleier', 'aktiv', 'oppsagt', 'utløpt', 'terminert', 'arkivert'],
        'tenant_signature_method' => ['elektronisk', 'bankid', 'manual'],
        'landlord_signature_method' => ['elektronisk', 'bankid', 'manual'],
        'signature_verification_method' => ['bankid_sso_fresh', 'qr_webauthn'],
        'holding_transfer_status' => ['not_transferred', 'transferred', 'transfer_failed'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'contract_type', 'start_date'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
