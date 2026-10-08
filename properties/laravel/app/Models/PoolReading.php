<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten PoolReading (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $serial_number
 * @property mixed $unit_name
 * @property mixed $water_temperature
 * @property mixed $air_temperature
 * @property mixed $solar_temperature
 * @property mixed $water_temperature_required
 * @property mixed $ph
 * @property mixed $ph_required
 * @property mixed $redox
 * @property mixed $redox_required
 * @property mixed $cl_free
 * @property mixed $cl_bounded
 * @property mixed $salinity
 * @property mixed $electrode_power
 * @property mixed $filter_flow_speed
 * @property mixed $filter_pressure
 * @property mixed $water_level
 * @property mixed $mode
 * @property mixed $filtration_speed
 * @property mixed $pool_flow
 * @property mixed $water_level_state
 * @property mixed $electrolyzer_direction
 * @property mixed $raw_data
 * @property mixed $pushed_to_smart_home
 * @property mixed $smart_home_response
 */
class PoolReading extends Model
{
    use Base44Entity, SoftDeletes;

    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'PoolReading';

    protected $table = 'pool_readings';

    protected $fillable = [
        'serial_number',
        'unit_name',
        'water_temperature',
        'air_temperature',
        'solar_temperature',
        'water_temperature_required',
        'ph',
        'ph_required',
        'redox',
        'redox_required',
        'cl_free',
        'cl_bounded',
        'salinity',
        'electrode_power',
        'filter_flow_speed',
        'filter_pressure',
        'water_level',
        'mode',
        'filtration_speed',
        'pool_flow',
        'water_level_state',
        'electrolyzer_direction',
        'raw_data',
        'pushed_to_smart_home',
        'smart_home_response',
    ];

    protected $casts = [
        'water_temperature' => 'float',
        'air_temperature' => 'float',
        'solar_temperature' => 'float',
        'water_temperature_required' => 'float',
        'ph' => 'float',
        'ph_required' => 'float',
        'redox' => 'float',
        'redox_required' => 'float',
        'cl_free' => 'float',
        'cl_bounded' => 'float',
        'salinity' => 'float',
        'electrode_power' => 'float',
        'filter_flow_speed' => 'float',
        'filter_pressure' => 'float',
        'water_level' => 'float',
        'raw_data' => 'array',
        'pushed_to_smart_home' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [

    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['serial_number'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
