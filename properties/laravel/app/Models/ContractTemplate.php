<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ContractTemplate (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $description
 * @property mixed $contract_type
 * @property mixed $is_office_template
 * @property mixed $default_lessor_company_id
 * @property mixed $default_invoice_bearer_company_id
 * @property mixed $duration_type
 * @property mixed $notice_period_months
 * @property mixed $auto_renew
 * @property mixed $renewal_period_months
 * @property mixed $kpi_adjustment
 * @property mixed $deposit_months
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
 * @property mixed $shared_costs_description
 * @property mixed $house_rules
 * @property mixed $office_attachments
 * @property mixed $terms
 * @property mixed $tags
 * @property mixed $is_active
 */
class ContractTemplate extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ContractTemplate';

    protected $table = 'contract_templates';

    protected $fillable = [
        'name',
        'description',
        'contract_type',
        'is_office_template',
        'default_lessor_company_id',
        'default_invoice_bearer_company_id',
        'duration_type',
        'notice_period_months',
        'auto_renew',
        'renewal_period_months',
        'kpi_adjustment',
        'deposit_months',
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
        'shared_costs_description',
        'house_rules',
        'office_attachments',
        'terms',
        'tags',
        'is_active',
    ];

    protected $casts = [
        'is_office_template' => 'boolean',
        'notice_period_months' => 'float',
        'auto_renew' => 'boolean',
        'renewal_period_months' => 'float',
        'kpi_adjustment' => 'boolean',
        'deposit_months' => 'float',
        'part_time_days_per_week' => 'float',
        'included_parking_spots' => 'float',
        'extra_parking_per_day' => 'float',
        'meeting_room_access' => 'boolean',
        'meeting_room_per_hour' => 'float',
        'meeting_room_per_day' => 'float',
        'common_areas' => 'array',
        'office_attachments' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'contract_type' => ['korttid', 'langtid'],
        'duration_type' => ['tidsbestemt', 'løpende'],
        'rental_scope' => ['heltid', 'deltid'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'contract_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
