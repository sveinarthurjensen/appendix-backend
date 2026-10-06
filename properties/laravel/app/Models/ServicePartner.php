<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ServicePartner (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $contact_person
 * @property mixed $category
 * @property mixed $email
 * @property mixed $phone
 * @property mixed $address
 * @property mixed $org_number
 * @property mixed $hourly_rate
 * @property mixed $emergency_available
 * @property mixed $assigned_properties
 * @property mixed $contract_url
 * @property mixed $rating
 * @property mixed $status
 * @property mixed $notes
 */
class ServicePartner extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ServicePartner';

    protected $table = 'service_partners';

    protected $fillable = [
        'name',
        'contact_person',
        'category',
        'email',
        'phone',
        'address',
        'org_number',
        'hourly_rate',
        'emergency_available',
        'assigned_properties',
        'contract_url',
        'rating',
        'status',
        'notes',
    ];

    protected $casts = [
        'hourly_rate' => 'float',
        'emergency_available' => 'boolean',
        'assigned_properties' => 'array',
        'rating' => 'float',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'category' => ['rørlegger', 'elektriker', 'hvac', 'brannvern', 'medisinsk_teknisk', 'renhold', 'vaktmester', 'låsesmed', 'glassmester', 'maler', 'snekker', 'taklegger', 'hage', 'it', 'sikkerhet', 'annet'],
        'status' => ['aktiv', 'inaktiv'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'category', 'phone'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
