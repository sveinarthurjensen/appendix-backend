<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ServiceSchedule (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $title
 * @property mixed $description
 * @property mixed $type
 * @property mixed $property_id
 * @property mixed $equipment_id
 * @property mixed $service_partner_id
 * @property mixed $frequency
 * @property mixed $scheduled_date
 * @property mixed $auto_generate
 * @property mixed $next_due_date
 * @property mixed $last_generated_date
 * @property mixed $lead_days
 * @property mixed $reminder_days_before
 * @property mixed $status
 * @property mixed $completed_date
 * @property mixed $completed_by
 * @property mixed $result
 * @property mixed $cost
 * @property mixed $invoice_url
 * @property mixed $report_url
 * @property mixed $notes
 * @property mixed $is_recurring
 * @property mixed $default_priority
 * @property mixed $default_staff_id
 */
class ServiceSchedule extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ServiceSchedule';

    protected $table = 'service_schedules';

    protected $fillable = [
        'title',
        'description',
        'type',
        'property_id',
        'equipment_id',
        'service_partner_id',
        'frequency',
        'scheduled_date',
        'auto_generate',
        'next_due_date',
        'last_generated_date',
        'lead_days',
        'reminder_days_before',
        'status',
        'completed_date',
        'completed_by',
        'result',
        'cost',
        'invoice_url',
        'report_url',
        'notes',
        'is_recurring',
        'default_priority',
        'default_staff_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date:Y-m-d',
        'auto_generate' => 'boolean',
        'next_due_date' => 'date:Y-m-d',
        'last_generated_date' => 'datetime',
        'lead_days' => 'float',
        'reminder_days_before' => 'float',
        'completed_date' => 'date:Y-m-d',
        'cost' => 'float',
        'is_recurring' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'type' => ['service', 'inspeksjon', 'kontroll', 'vedlikehold', 'årlig_kontroll', 'elektrokontroll', 'brannvern', 'vvs', 'emu_kontroll'],
        'frequency' => ['engangs', 'ukentlig', 'månedlig', 'kvartalsvis', 'halvårlig', 'årlig', 'hvert_2_år', 'hvert_5_år'],
        'status' => ['planlagt', 'påminnelse_sendt', 'bestilt', 'utført', 'utsatt', 'kansellert'],
        'result' => ['godkjent', 'avvik', 'kritisk_avvik', 'ikke_utført'],
        'default_priority' => ['lav', 'medium', 'høy', 'akutt'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['title', 'type', 'property_id', 'scheduled_date'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
