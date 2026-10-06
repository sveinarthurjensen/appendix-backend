<?php

namespace App\Models;

use App\Models\Concerns\Base44Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generert fra Base44-entiteten NotificationTemplate (appendix_properties).
 * Ikke rediger for hånd – legg relasjoner/forretningslogikk i en trait eller endre generatoren.
 * @property mixed $name
 * @property mixed $type
 * @property mixed $channel
 * @property mixed $subject
 * @property mixed $email_body
 * @property mixed $sms_body
 * @property mixed $trigger
 * @property mixed $days_before
 * @property mixed $target_segment
 * @property mixed $is_active
 */
class NotificationTemplate extends Model
{
    use Base44Entity, SoftDeletes;

    public const APP_ID = 'appendix_properties';
    public const ENTITY = 'NotificationTemplate';

    protected $table = 'notification_templates';

    protected $fillable = [
        'name',
        'type',
        'channel',
        'subject',
        'email_body',
        'sms_body',
        'trigger',
        'days_before',
        'target_segment',
        'is_active',
    ];

    protected $casts = [
        'days_before' => 'float',
        'is_active' => 'boolean',
    ];

    /** Feltnavn → tillatte verdier (fra enum i skjemaet) */
    public const ENUMS = [
        'type' => ['payment_reminder', 'booking_confirmation', 'contract_confirmation', 'house_rules', 'marketing_offer', 'event_reminder', 'custom'],
        'channel' => ['email', 'sms', 'both'],
        'trigger' => ['manual', 'automatic', 'scheduled'],
        'target_segment' => ['all', 'short_term', 'long_term', 'vip', 'new', 'custom'],
    ];

    /** Påkrevde felt ved opprettelse */
    public const REQUIRED = ['name', 'type', 'channel'];

    /** Felt med read-RLS på rad-nivå: [felt => brukerattributt] – brukes av scopeVisibleTo */
    public const OWNER_FIELDS = [];
}
