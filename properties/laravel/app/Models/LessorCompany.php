<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten LessorCompany (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $org_number
 * @property mixed $industry
 * @property mixed $ownership_share
 * @property mixed $employees
 * @property mixed $revenue_m
 * @property mixed $compliance_status
 * @property mixed $active_status
 * @property mixed $address
 * @property mixed $is_private
 * @property mixed $contact_name
 * @property mixed $contact_email
 * @property mixed $contact_phone
 * @property mixed $default_for_regions
 * @property mixed $is_invoice_bearer
 * @property mixed $notes
 */
class LessorCompany extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'LessorCompany';

    protected $table = 'lessor_companies';

    protected $fillable = [
        'name',
        'org_number',
        'industry',
        'ownership_share',
        'employees',
        'revenue_m',
        'compliance_status',
        'active_status',
        'address',
        'is_private',
        'contact_name',
        'contact_email',
        'contact_phone',
        'default_for_regions',
        'is_invoice_bearer',
        'notes',
    ];

    protected $casts = [
        'ownership_share' => 'float',
        'employees' => 'float',
        'revenue_m' => 'float',
        'is_private' => 'boolean',
        'default_for_regions' => 'array',
        'is_invoice_bearer' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'industry' => ['helse', 'jus', 'eiendom', 'annet'],
        'compliance_status' => ['compliant', 'mangler'],
        'active_status' => ['aktiv', 'hvilende'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
