<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten DataBackup (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $backup_date
 * @property mixed $backup_type
 * @property mixed $file_name
 * @property mixed $file_size
 * @property mixed $entity_count
 * @property mixed $record_count
 * @property mixed $status
 * @property mixed $destination_results
 * @property mixed $created_by_name
 * @property mixed $error_message
 */
class DataBackup extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'DataBackup';

    protected $table = 'data_backups';

    protected $fillable = [
        'backup_date',
        'backup_type',
        'file_name',
        'file_size',
        'entity_count',
        'record_count',
        'status',
        'destination_results',
        'created_by_name',
        'error_message',
    ];

    protected $casts = [
        'backup_date' => 'datetime',
        'file_size' => 'float',
        'entity_count' => 'float',
        'record_count' => 'float',
        'destination_results' => 'array',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'backup_type' => ['manual', 'scheduled', 'onedrive', 'synology', 'all'],
        'status' => ['in_progress', 'success', 'failed', 'partial'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['backup_date', 'backup_type', 'status'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
