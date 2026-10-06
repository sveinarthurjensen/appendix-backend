<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten VaultEntry (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $title
 * @property mixed $entry_type
 * @property mixed $property_id
 * @property mixed $property_name
 * @property mixed $description
 * @property mixed $encrypted_value
 * @property mixed $iv
 * @property mixed $username
 * @property mixed $url
 * @property mixed $encrypted_notes
 * @property mixed $notes_iv
 * @property mixed $shared_with
 * @property mixed $access_level
 * @property mixed $last_accessed
 * @property mixed $last_accessed_by
 * @property mixed $is_active
 */
class VaultEntry extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'VaultEntry';

    protected $table = 'vault_entries';

    protected $fillable = [
        'title',
        'entry_type',
        'property_id',
        'property_name',
        'description',
        'encrypted_value',
        'iv',
        'username',
        'url',
        'encrypted_notes',
        'notes_iv',
        'shared_with',
        'access_level',
        'last_accessed',
        'last_accessed_by',
        'is_active',
    ];

    protected $casts = [
        'shared_with' => 'array',
        'last_accessed' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'entry_type' => ['passord', 'kode', 'alarm', 'wifi', 'nokkel', 'bruker', 'annet'],
        'access_level' => ['admin', 'utvalgt'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['title', 'entry_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
