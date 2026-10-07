<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\ContractEvent;

/**
 * Portert fra base44/functions/contractStatusGuard/entry.ts (+ logContractEvent/canActivate fra
 * base44/shared/contractLifecycle.ts)
 *
 * Base44-workflowen «Contract status guard (update)» var en entity-trigger på Contract.update.
 * Her: Eloquent-observer på `updated` (registrert i App\Providers\FunctionsServiceProvider).
 *
 * Forhindrer ugyldige statusoverganger:
 *  - status="aktiv" uten at begge har signert → korrigeres tilbake til signert_leietaker /
 *    signert_utleier / forrige status / utkast, og det logges ContractEvent «redigert»
 *    («Aktivering blokkert …»).
 *  - Logger ContractEvent ved statusendringer (sendt/aktivert/oppsagt/…), og «redigert» ved
 *    innholdsendring uten statusendring.
 *
 * Som i originalen kjøres dette ETTER at oppdateringen er lagret; korrigeringen skjer med
 * saveQuietly() så observeren ikke trigger seg selv.
 */
class ContractObserver
{
    private const EVENT_MAP = [
        'sendt' => 'sendt',
        'signert_leietaker' => 'signert_leietaker',
        'signert_utleier' => 'signert_utleier',
        'aktiv' => 'aktivert',
        'oppsagt' => 'oppsagt',
        'utløpt' => 'utløpt',
        'terminert' => 'terminert',
        'arkivert' => 'arkivert',
    ];

    public function updated(Contract $contract): void
    {
        $prevStatus = $contract->getOriginal('status');
        $newStatus = $contract->status;

        if ($newStatus === 'aktiv' && !self::canActivate($contract)) {
            $correction = null;
            if ($contract->signed_by_landlord_date) {
                $correction = 'signert_utleier';
            } elseif ($contract->signed_by_tenant_date) {
                $correction = 'signert_leietaker';
            } else {
                $correction = ($prevStatus && $prevStatus !== 'aktiv') ? $prevStatus : 'utkast';
            }

            $contract->status = $correction;
            $contract->saveQuietly();
            self::logContractEvent($contract->id, 'redigert', [
                'detail' => "Aktivering blokkert — mangler signatur. Korrigert til {$correction}.",
                'previous_status' => $prevStatus,
                'new_status' => $correction,
            ]);
            return;
        }

        if ($newStatus && $newStatus !== $prevStatus) {
            self::logContractEvent($contract->id, self::EVENT_MAP[$newStatus] ?? 'redigert', [
                'previous_status' => $prevStatus,
                'new_status' => $newStatus,
                'detail' => 'Manuell statusendring',
            ]);
            return;
        }

        // Innholdsendring uten statusendring (JSON.stringify(data) !== JSON.stringify(old_data))
        $changed = array_diff(array_keys($contract->getChanges()), [Contract::UPDATED_AT]);
        if ($prevStatus && $changed) {
            self::logContractEvent($contract->id, 'redigert', [
                'detail' => 'Kontrakt redigert',
                'previous_status' => $prevStatus,
                'new_status' => $newStatus,
            ]);
        }
    }

    /** «aktiv» krever at begge parter har signert. */
    public static function canActivate(Contract $c): bool
    {
        return (bool) ($c->signed_by_landlord_date && $c->signed_by_tenant_date);
    }

    /** logContractEvent fra contractLifecycle.ts – best-effort, må aldri feile hovedflyten. */
    public static function logContractEvent(string $contractId, string $eventType, array $opts = []): void
    {
        try {
            ContractEvent::create([
                'contract_id' => $contractId,
                'event_type' => $eventType,
                'actor_user_id' => $opts['actor_user_id'] ?? null,
                'actor_email' => $opts['actor_email'] ?? (auth()->user()?->email),
                'actor_role' => $opts['actor_role'] ?? 'system',
                'detail' => $opts['detail'] ?? '',
                'previous_status' => $opts['previous_status'] ?? null,
                'new_status' => $opts['new_status'] ?? null,
                'version_number' => $opts['version_number'] ?? null,
            ]);
        } catch (\Throwable) {
            // Swallow — logging må aldri feile hovedflyten.
        }
    }
}
