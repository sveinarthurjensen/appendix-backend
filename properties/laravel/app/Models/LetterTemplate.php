<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten LetterTemplate (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $category
 * @property mixed $subject
 * @property mixed $body_template
 * @property mixed $variables
 * @property mixed $is_active
 */
class LetterTemplate extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'LetterTemplate';

    protected $table = 'letter_templates';

    protected $fillable = [
        'name',
        'category',
        'subject',
        'body_template',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'category' => ['klage', 'tilbud', 'varsel', 'purring', 'kvittering', 'svar', 'generelt', 'annet'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'category'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
