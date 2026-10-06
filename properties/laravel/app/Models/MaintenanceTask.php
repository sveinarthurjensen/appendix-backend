<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten MaintenanceTask (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $property_id
 * @property mixed $staff_id
 * @property mixed $service_partner_id
 * @property mixed $service_schedule_id
 * @property mixed $source
 * @property mixed $title
 * @property mixed $description
 * @property mixed $category
 * @property mixed $priority
 * @property mixed $status
 * @property mixed $scheduled_date
 * @property mixed $due_date
 * @property mixed $completed_date
 * @property mixed $estimated_cost
 * @property mixed $actual_cost
 * @property mixed $invoice_url
 * @property mixed $invoice_number
 * @property mixed $photos_before
 * @property mixed $photos_after
 * @property mixed $attachments
 * @property mixed $checklist
 * @property mixed $notes
 * @property mixed $reported_by_tenant
 * @property mixed $tenant_id
 */
class MaintenanceTask extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'MaintenanceTask';

    protected $table = 'maintenance_tasks';

    protected $fillable = [
        'property_id',
        'staff_id',
        'service_partner_id',
        'service_schedule_id',
        'source',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'scheduled_date',
        'due_date',
        'completed_date',
        'estimated_cost',
        'actual_cost',
        'invoice_url',
        'invoice_number',
        'photos_before',
        'photos_after',
        'attachments',
        'checklist',
        'notes',
        'reported_by_tenant',
        'tenant_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'completed_date' => 'date:Y-m-d',
        'estimated_cost' => 'float',
        'actual_cost' => 'float',
        'photos_before' => 'array',
        'photos_after' => 'array',
        'attachments' => 'array',
        'checklist' => 'array',
        'reported_by_tenant' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'source' => ['manual', 'service_schedule', 'inspeksjon', 'tenant_report'],
        'category' => ['reparasjon', 'vedlikehold', 'rengjøring', 'inspeksjon', 'oppussing', 'annet'],
        'priority' => ['lav', 'medium', 'høy', 'akutt'],
        'status' => ['ny', 'planlagt', 'pågår', 'fullført', 'kansellert'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['property_id', 'title', 'category'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
