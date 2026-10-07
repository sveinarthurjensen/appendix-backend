<?php

namespace App\Functions;

use App\Mail\PlainMail;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Portert fra base44/functions/contractDeadlineReminders/entry.ts
 *
 * Planlagt jobb. To Base44-workflows kalte denne daglig: «Daglig kontraktsfrister-påminnelse»
 * (30 5 * * * UTC) og «Contract deadline reminders (daily)» (0 6 * * * UTC). Sjekker:
 *  0. Auto-utløp: aktiv tidsbestemt kontrakt uten auto_renew med passert sluttdato → 'utløpt'
 *  1. Signatur-påminnelse for kontrakter i status 'sendt' uten begge signaturer
 *  2. Sluttdato innen 90 dager (med KPI-notat ved auto_renew)
 *  3. Oppsigelsesfrist (termination_notice_date) innen 30 dager
 * E-post til admin (services.reminders.admin_email) og leietaker.
 *
 * Avvik: Core.SendEmail (ren tekst) → Laravel Mail (PlainMail) med teksten i <pre>.
 * Status-oppdatering til 'utløpt' trigger ContractObserver, som logger ContractEvent.
 * Svar: {success, reminders_sent, details: [{contract_id, type, ...}]}
 */
class ContractDeadlineReminders extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        if ($user && !$user->hasRole('admin')) {
            throw new FunctionException('Forbidden', 403);
        }

        $adminEmail = config('services.reminders.admin_email');
        if (!$adminEmail) {
            throw new FunctionException('REMINDER_ADMIN_EMAIL ikke satt', 500);
        }

        $now = now();
        $in30 = $now->copy()->addDays(30);
        $in90 = $now->copy()->addDays(90);
        $reminders = [];
        $n = fn ($v) => number_format((float) $v, 0, ',', ' ');

        // 0. Auto-utløp
        $activeContracts = Contract::base44Filter(['contract_type' => 'langtid', 'status' => 'aktiv'])->get();
        foreach ($activeContracts as $c) {
            if ($c->end_date && !$c->auto_renew) {
                $endDate = $this->date($c->end_date);
                if ($endDate && $endDate->lt($now)) {
                    $c->update(['status' => 'utløpt']);
                    $reminders[] = ['contract_id' => $c->id, 'type' => 'auto_expired'];
                }
            }
        }

        $contracts = Contract::base44Filter(['contract_type' => 'langtid', 'status' => 'aktiv'])->get();
        $sentContracts = Contract::base44Filter(['contract_type' => 'langtid', 'status' => 'sendt'])->get();
        $tenants = Tenant::all()->keyBy('id');
        $properties = Property::all()->keyBy('id');

        // 1. Signatur-påminnelse
        foreach ($sentContracts as $contract) {
            if ($contract->signed_by_landlord_date && $contract->signed_by_tenant_date) {
                continue;
            }
            $property = $properties->get($contract->property_id);
            $tenant = $tenants->get($contract->tenant_id);
            $propertyName = $property?->name ?: 'Ukjent eiendom';
            $missing = [];
            if (!$contract->signed_by_landlord_date) {
                $missing[] = 'utleier';
            }
            if (!$contract->signed_by_tenant_date) {
                $missing[] = 'leietaker';
            }
            $subject = "🔔 Signatur mangler – {$propertyName}";
            $body = "Hei,\n\nKontrakten for {$propertyName} er sendt til signering, men mangler fortsatt signatur fra: " . implode(' og ', $missing)
                . ".\n\nLogg inn i Appendix Properties for å signere med BankID.\n\nMed vennlig hilsen\nAppendix Properties (automatisk varsling)";
            $this->send($adminEmail, $subject, $body);
            if ($tenant?->email) {
                $this->send($tenant->email, $subject, $body);
            }
            $reminders[] = ['contract_id' => $contract->id, 'type' => 'signature_reminder', 'missing' => $missing];
        }

        foreach ($contracts as $contract) {
            $property = $properties->get($contract->property_id);
            $tenant = $tenants->get($contract->tenant_id);
            $propertyName = $property?->name ?: 'Ukjent eiendom';
            $tenantName = $tenant ? trim("{$tenant->first_name} {$tenant->last_name}") : 'Ukjent leietaker';
            $rent = (float) ($contract->monthly_rent ?: 0);

            // 2. Sluttdato nærmer seg (tidsbestemt)
            $endDate = $this->date($contract->end_date);
            if ($endDate && $endDate->gte($now) && $endDate->lte($in90)) {
                $daysLeft = (int) ceil($now->diffInSeconds($endDate, false) / 86400);

                $kpiNote = $contract->auto_renew
                    ? "\n\n📊 KPI-JUSTERING:\nDenne kontrakten fornyes automatisk. Husk å vurdere leiejustering iht. KPI.\nSiste 12-måneders KPI (SSB) ligger typisk mellom 2-5%. Ved 3,5% KPI-økning:\nNy månedlig leie: {$n(round($rent * 1.035))} kr (fra {$n($rent)} kr)\n\nFor å justere leien, rediger kontrakten og send oppdatert avtaletekst til leietaker."
                    : '';

                $subject = "⚠️ Kontrakt utløper om {$daysLeft} dager – {$propertyName}";
                $body = "Hei,\n\nDette er en påminnelse om at leiekontrakten for {$propertyName} (leietaker: {$tenantName}) utløper {$endDate->format('d.m.Y')} – om {$daysLeft} dager.\n\nKontraktsdetaljer:\n- Månedlig leie: {$n($rent)} kr\n- Depositum: {$n($contract->deposit_amount ?: 0)} kr\n- Oppsigelsestid: " . ($contract->notice_period_months ?: 3) . " måneder{$kpiNote}\n\nLogg inn i Appendix Properties for å behandle kontrakten.\n\nMed vennlig hilsen\nAppendix Properties (automatisk varsling)";

                $this->send($adminEmail, $subject, $body);
                if ($tenant?->email) {
                    $this->send(
                        $tenant->email,
                        "Leiekontrakten din utløper om {$daysLeft} dager – {$propertyName}",
                        "Hei " . ($tenant->first_name ?: '') . ",\n\nDette er en påminnelse om at leiekontrakten din for {$propertyName} utløper {$endDate->format('d.m.Y')} – om {$daysLeft} dager.\n\nTa kontakt med utleier om du ønsker å forlenge leieforholdet.\n\nMed vennlig hilsen\nAppendix Properties",
                    );
                }
                $reminders[] = ['contract_id' => $contract->id, 'type' => 'end_date', 'days_left' => $daysLeft];
            }

            // 3. Oppsigelsesfrist innen 30 dager
            $noticeDate = $this->date($contract->termination_notice_date);
            if ($noticeDate && $noticeDate->gte($now) && $noticeDate->lte($in30)) {
                $daysLeft = (int) ceil($now->diffInSeconds($noticeDate, false) / 86400);
                $subject = "🔔 Oppsigelsefrist om {$daysLeft} dager – {$propertyName}";
                $body = "Hei,\n\nHusk at oppsigelsesfristen for {$propertyName} ({$tenantName}) er {$noticeDate->format('d.m.Y')} – om {$daysLeft} dager.\n\nHvis du ikke ønsker å forlenge kontrakten, må oppsigelse sendes nå.\n\nLogg inn i Appendix Properties for å behandle kontrakten.";

                $this->send($adminEmail, $subject, $body);
                if ($tenant?->email) {
                    $this->send(
                        $tenant->email,
                        "Påminnelse: oppsigelsesfrist for leiekontrakten – {$propertyName}",
                        "Hei " . ($tenant->first_name ?: '') . ",\n\nOppsigelsesfristen for din leiekontrakt for {$propertyName} er {$noticeDate->format('d.m.Y')}. Ta kontakt om du har spørsmål.\n\nMed vennlig hilsen\nAppendix Properties",
                    );
                }
                $reminders[] = ['contract_id' => $contract->id, 'type' => 'notice_deadline', 'days_left' => $daysLeft];
            }
        }

        return ['success' => true, 'reminders_sent' => count($reminders), 'details' => $reminders];
    }

    private function date(mixed $v): ?Carbon
    {
        if (!$v) {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function send(string $to, string $subject, string $text): void
    {
        $html = '<pre style="font-family:Arial,sans-serif;font-size:14px;white-space:pre-wrap;color:#1e293b">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</pre>';
        Mail::to($to)->send(new PlainMail($subject, $html));
    }
}
