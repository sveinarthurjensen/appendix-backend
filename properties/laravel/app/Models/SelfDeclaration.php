<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten SelfDeclaration (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $booking_id
 * @property mixed $guest_id
 * @property mixed $property_id
 * @property mixed $check_in_date
 * @property mixed $check_out_date
 * @property mixed $num_guests
 * @property mixed $purpose
 * @property mixed $confirmed_responsible_use
 * @property mixed $confirmed_house_rules
 * @property mixed $confirmed_terms
 * @property mixed $submitted_at
 */
class SelfDeclaration extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'SelfDeclaration';

    protected $table = 'self_declarations';

    protected $fillable = [
        'booking_id',
        'guest_id',
        'property_id',
        'check_in_date',
        'check_out_date',
        'num_guests',
        'purpose',
        'confirmed_responsible_use',
        'confirmed_house_rules',
        'confirmed_terms',
        'submitted_at',
    ];

    protected $casts = [
        'check_in_date' => 'date:Y-m-d',
        'check_out_date' => 'date:Y-m-d',
        'num_guests' => 'float',
        'confirmed_responsible_use' => 'boolean',
        'confirmed_house_rules' => 'boolean',
        'confirmed_terms' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['guest_id', 'property_id', 'check_in_date', 'check_out_date', 'num_guests', 'purpose', 'confirmed_responsible_use', 'confirmed_house_rules', 'confirmed_terms'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = ['guest_id' => 'id'];
}
