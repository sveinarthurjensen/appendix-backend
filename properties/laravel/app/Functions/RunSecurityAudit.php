<?php

namespace App\Functions;

use App\Models\SecurityAudit;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portert fra base44/functions/runSecurityAudit/entry.ts
 *
 * Planlagt jobb (workflow «Daglig RLS-sikkerhetsaudit», cron 0 0 * * * UTC). Kan også kjøres
 * manuelt av admin.
 *
 * AVVIK: Originalen analyserte Base44-entitetenes RLS-manifest. Det finnes ikke i Laravel
 * (tilgang styres av policies/roller), så dette er en forenklet audit av brukere/roller/tokens:
 *  - antall admin-brukere (0 = critical, > services.security.max_admins = warning)
 *  - brukere uten rolle (warning)
 *  - admin-brukere som ikke er admin_approved (warning)
 *  - UserRole-rader (aktiv) uten tilhørende bruker (info) / UserRole admin uten User.role=admin (warning)
 *  - Sanctum API-tokens (personal_access_tokens) eldre enn 90 dager (warning) og ubrukt > 90 dager (info)
 * Skriver én SecurityAudit-rad med samme feltnavn som før (findings: entity_name, operation,
 * problem_type, severity, detail). manifest_version = 'laravel-policies-1'.
 *
 * Svar: {success, audit_id, status, entities_checked, issues_found, critical, warnings, trigger, triggered_by}
 */
class RunSecurityAudit extends Base44Function
{
    public const MANIFEST_VERSION = 'laravel-policies-1';
    private const TOKEN_MAX_AGE_DAYS = 90;

    public function __invoke(?User $user, array $payload): array
    {
        $isScheduled = $user === null;
        $actorEmail = 'system';
        if ($user) {
            $this->requireAdmin($user);
            $actorEmail = $user->email ?: ($user->full_name ?: 'admin');
        }

        $findings = [];
        $checked = 0;
        $maxAdmins = (int) config('services.security.max_admins', 5);

        // 1. Brukere og roller
        $users = User::all();
        $checked++;
        $admins = $users->filter(fn ($u) => $u->role === 'admin');
        if ($admins->isEmpty()) {
            $findings[] = $this->f('User', 'read', 'no_admin', 'critical', 'Ingen brukere har rollen admin — ingen kan administrere appen.');
        } elseif ($admins->count() > $maxAdmins) {
            $findings[] = $this->f('User', 'update', 'many_admins', 'warning',
                "{$admins->count()} brukere har rollen admin (grense {$maxAdmins}): " . $admins->pluck('email')->implode(', '));
        }
        foreach ($users as $u) {
            if (!$u->role) {
                $findings[] = $this->f('User', 'read', 'missing_role', 'warning', "Bruker {$u->email} har ingen rolle.");
            }
            if ($u->role === 'admin' && $u->admin_approved === false) {
                $findings[] = $this->f('User', 'update', 'unapproved_admin', 'warning', "Admin-bruker {$u->email} er ikke admin_approved.");
            }
        }

        // 2. UserRole mot User
        try {
            $checked++;
            $byEmail = $users->keyBy(fn ($u) => strtolower((string) $u->email));
            foreach (UserRole::base44Filter(['status' => 'aktiv'])->get() as $r) {
                $email = strtolower((string) $r->user_email);
                $u = $byEmail->get($email);
                if (!$u) {
                    $findings[] = $this->f('UserRole', 'read', 'orphan_role', 'info', "Aktiv UserRole ({$r->role}) for {$email} uten tilhørende bruker.");
                } elseif ($r->role === 'admin' && $u->role !== 'admin') {
                    $findings[] = $this->f('UserRole', 'update', 'role_mismatch', 'warning', "UserRole admin for {$email}, men User.role er «{$u->role}».");
                }
            }
        } catch (\Throwable $e) {
            $findings[] = $this->f('UserRole', 'read', 'audit_error', 'info', 'Kunne ikke lese UserRole: ' . $e->getMessage());
        }

        // 3. API-tokens (Laravel Sanctum)
        try {
            if (Schema::hasTable('personal_access_tokens')) {
                $checked++;
                $cutoff = now()->subDays(self::TOKEN_MAX_AGE_DAYS);
                $old = DB::table('personal_access_tokens')->where('created_at', '<', $cutoff)->get();
                foreach ($old as $t) {
                    $exp = $t->expires_at ?? null;
                    if ($exp && \Carbon\Carbon::parse($exp)->isPast()) {
                        continue; // utløpt – ufarlig
                    }
                    $findings[] = $this->f('PersonalAccessToken', 'read', 'stale_token', 'warning',
                        "API-token «{$t->name}» (id {$t->id}, {$t->tokenable_type} {$t->tokenable_id}) er eldre enn " . self::TOKEN_MAX_AGE_DAYS . " dager (opprettet {$t->created_at}) og utløper ikke.");
                }
                $unused = DB::table('personal_access_tokens')
                    ->where(fn ($q) => $q->whereNull('last_used_at')->orWhere('last_used_at', '<', $cutoff))
                    ->where('created_at', '<', $cutoff)
                    ->count();
                if ($unused > 0) {
                    $findings[] = $this->f('PersonalAccessToken', 'read', 'unused_token', 'info',
                        "{$unused} API-token(s) er ikke brukt på " . self::TOKEN_MAX_AGE_DAYS . " dager — vurder å slette dem.");
                }
            }
        } catch (\Throwable $e) {
            $findings[] = $this->f('PersonalAccessToken', 'read', 'audit_error', 'info', 'Kunne ikke lese API-tokens: ' . $e->getMessage());
        }

        $critical = count(array_filter($findings, fn ($f) => $f['severity'] === 'critical'));
        $warnings = count(array_filter($findings, fn ($f) => $f['severity'] === 'warning'));
        $status = $critical > 0 ? 'critical' : ($warnings > 0 ? 'warnings' : 'ok');

        $audit = SecurityAudit::create([
            'run_at' => now(),
            'status' => $status,
            'entities_checked' => $checked,
            'issues_found' => count($findings),
            'findings' => $findings,
            'resolved' => false,
            'trigger' => $isScheduled ? 'scheduled' : 'manual',
            'manifest_version' => self::MANIFEST_VERSION,
        ]);

        return [
            'success' => true,
            'audit_id' => $audit->id,
            'status' => $status,
            'entities_checked' => $checked,
            'issues_found' => count($findings),
            'critical' => $critical,
            'warnings' => $warnings,
            'trigger' => $isScheduled ? 'scheduled' : 'manual',
            'triggered_by' => $actorEmail,
        ];
    }

    private function f(string $entity, string $op, string $type, string $severity, string $detail): array
    {
        return ['entity_name' => $entity, 'operation' => $op, 'problem_type' => $type, 'severity' => $severity, 'detail' => $detail];
    }
}
