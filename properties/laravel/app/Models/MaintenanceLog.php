<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten MaintenanceLog (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $title
 * @property mixed $description
 * @property mixed $type
 * @property mixed $property_id
 * @property mixed $equipment_id
 * @property mixed $schedule_id
 * @property mixed $performed_date
 * @property mixed $performed_by
 * @property mixed $service_partner_id
 * @property mixed $result
 * @property mixed $findings
 * @property mixed $actions_taken
 * @property mixed $follow_up_required
 * @property mixed $follow_up_date
 * @property mixed $cost
 * @property mixed $hours_spent
 * @property mixed $photos_before
 * @property mixed $photos_after
 * @property mixed $documents
 * @property mixed $signature_url
 * @property mixed $notes
 */
class MaintenanceLog extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'MaintenanceLog';

    protected $table = 'maintenance_logs';

    protected $fillable = [
        'title',
        'description',
        'type',
        'property_id',
        'equipment_id',
        'schedule_id',
        'performed_date',
        'performed_by',
        'service_partner_id',
        'result',
        'findings',
        'actions_taken',
        'follow_up_required',
        'follow_up_date',
        'cost',
        'hours_spent',
        'photos_before',
        'photos_after',
        'documents',
        'signature_url',
        'notes',
    ];

    protected $casts = [
        'performed_date' => 'date:Y-m-d',
        'follow_up_required' => 'boolean',
        'follow_up_date' => 'date:Y-m-d',
        'cost' => 'float',
        'hours_spent' => 'float',
        'photos_before' => 'array',
        'photos_after' => 'array',
        'documents' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'type' => ['vedlikehold', 'reparasjon', 'inspeksjon', 'kontroll', 'service', 'utbedring', 'oppgradering'],
        'result' => ['ok', 'avvik_funnet', 'utbedret', 'krever_oppfølging'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['title', 'type', 'property_id', 'performed_date'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
