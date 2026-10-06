<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\NotificationLog;
use App\Models\UserInviteEvent;

/**
 * Portert fra base44/shared/inviteEvents.ts (+ audit() fra oidcProvider.ts)
 *
 * Felles logger for invitasjonslivssyklusen (UserInvite). Skriver
 *  1) UserInviteEvent (statushistorikk)
 *  2) AuditEvent (audit-loggen)
 *  3) NotificationLog ved statusendringer admin bør varsles om (sendt/akseptert/utløpt)
 *
 * Brukes av: CreateUserInvite, ResolveInviteCode, LogInviteEvent (og OIDC-callback når den porteres).
 * Alle skriv er best-effort (feil svelges), som i originalen.
 */
class InviteEvents
{
    public const EVENT_TYPES = ['opprettet', 'sendt', 'åpnet', 'akseptert', 'utløpt', 'tilbakekalt'];

    private const NOTIFY_EVENTS = ['sendt', 'akseptert', 'utløpt'];

    private const EVENT_LABEL = [
        'opprettet' => 'Opprettet',
        'sendt' => 'Sendt',
        'åpnet' => 'Åpnet lenke',
        'akseptert' => 'Akseptert',
        'utløpt' => 'Utløpt',
        'tilbakekalt' => 'Tilbakekalt',
    ];

    public const ROLE_LABELS = ['admin' => 'Administrator', 'arbeider' => 'Arbeider', 'leietaker' => 'Leietaker'];

    public static function roleLabel(?string $role): string
    {
        return self::ROLE_LABELS[$role] ?? (string) $role;
    }

    /**
     * @param array{invite_id: string, event_type: string, actor_name?: string, actor_email?: string, invite_email?: string, detail?: string} $opts
     */
    public static function log(array $opts): void
    {
        $inviteId = (string) $opts['invite_id'];
        $eventType = (string) $opts['event_type'];
        $actorName = $opts['actor_name'] ?? 'System';
        $actorEmail = $opts['actor_email'] ?? '';
        $inviteEmail = $opts['invite_email'] ?? '';
        $detail = $opts['detail'] ?? '';
        $label = self::EVENT_LABEL[$eventType] ?? $eventType;
        $now = now();

        // 1) Statushistorikk
        try {
            UserInviteEvent::create([
                'invite_id' => $inviteId,
                'invite_email' => $inviteEmail,
                'event_type' => $eventType,
                'actor_name' => $actorName,
                'detail' => $detail,
            ]);
        } catch (\Throwable) {
        }

        // 2) Audit-logg
        try {
            AuditEvent::create([
                'event_type' => 'invite_' . $eventType,
                'actor_email' => $actorEmail ?: $actorName,
                'detail' => $detail ?: ($label . ' — ' . ($inviteEmail ?: $inviteId)),
                'success' => true,
                'created_at' => $now,
            ]);
        } catch (\Throwable) {
        }

        // 3) Varsler ved statusendring
        if (in_array($eventType, self::NOTIFY_EVENTS, true) && $inviteEmail) {
            try {
                NotificationLog::create([
                    'recipient_email' => $inviteEmail,
                    'channel' => 'email',
                    'subject' => 'Invitasjon: ' . $label,
                    'body' => $detail ?: ('Invitasjonen til ' . $inviteEmail . ' er nå ' . mb_strtolower($label) . '.'),
                    'status' => 'sent',
                    'sent_date' => $now,
                    'metadata' => ['invite_id' => $inviteId, 'event_type' => $eventType],
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * audit() fra base44/shared/oidcProvider.ts — generell AuditEvent, best-effort.
     * @param array{event_type: string, actor_user_id?: string, actor_email?: string, nin_hash?: string, ip?: string, detail?: string, success?: bool} $ev
     */
    public static function audit(array $ev): void
    {
        try {
            AuditEvent::create([
                'event_type' => $ev['event_type'],
                'actor_user_id' => $ev['actor_user_id'] ?? '',
                'actor_email' => $ev['actor_email'] ?? '',
                'nin_hash' => $ev['nin_hash'] ?? '',
                'ip' => $ev['ip'] ?? '',
                'detail' => $ev['detail'] ?? '',
                'success' => ($ev['success'] ?? true) !== false,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /** Date.parse(expires_at) < now – håndterer både Carbon og streng. */
    public static function isExpired(mixed $expiresAt): bool
    {
        if (!$expiresAt) {
            return false;
        }
        try {
            return now()->greaterThan(\Carbon\Carbon::parse($expiresAt));
        } catch (\Throwable) {
            return false;
        }
    }
}
