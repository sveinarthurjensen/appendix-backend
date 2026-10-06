<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten SecurityAudit (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $run_at
 * @property mixed $status
 * @property mixed $entities_checked
 * @property mixed $issues_found
 * @property mixed $findings
 * @property mixed $resolved
 * @property mixed $resolved_by
 * @property mixed $resolved_at
 * @property mixed $trigger
 * @property mixed $manifest_version
 */
class SecurityAudit extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'SecurityAudit';

    protected $table = 'security_audits';

    protected $fillable = [
        'run_at',
        'status',
        'entities_checked',
        'issues_found',
        'findings',
        'resolved',
        'resolved_by',
        'resolved_at',
        'trigger',
        'manifest_version',
    ];

    protected $casts = [
        'run_at' => 'datetime',
        'entities_checked' => 'float',
        'issues_found' => 'float',
        'findings' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'status' => ['ok', 'warnings', 'critical'],
        'trigger' => ['scheduled', 'manual'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['run_at', 'status', 'entities_checked', 'issues_found'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
