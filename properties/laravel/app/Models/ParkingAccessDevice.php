<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten ParkingAccessDevice (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $spot_id
 * @property mixed $device_type
 * @property mixed $access_code
 * @property mixed $webhook_url
 * @property mixed $webhook_method
 * @property mixed $webhook_payload
 * @property mixed $manufacturer
 * @property mixed $status
 * @property mixed $notes
 */
class ParkingAccessDevice extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'ParkingAccessDevice';

    protected $table = 'parking_access_devices';

    protected $fillable = [
        'name',
        'spot_id',
        'device_type',
        'access_code',
        'webhook_url',
        'webhook_method',
        'webhook_payload',
        'manufacturer',
        'status',
        'notes',
    ];

    protected $casts = [

    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'device_type' => ['bom', 'port', 'låssystem', 'app_kontroll', 'annet'],
        'webhook_method' => ['GET', 'POST'],
        'status' => ['aktiv', 'inaktiv', 'feil'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'device_type'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
