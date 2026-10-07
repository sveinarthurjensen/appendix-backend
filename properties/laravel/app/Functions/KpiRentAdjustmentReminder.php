<?php

namespace App\Functions;

use App\Mail\PlainMail;
use App\Models\Contract;
use App\Models\NotificationLog;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Portert fra base44/functions/kpiRentAdjustmentReminder/entry.ts
 *
 * Planlagt jobb (workflow «Årlig KPI-leiejustering påminnelse», cron 0 6 1 * * UTC – dvs. den
 * 1. hver måned, ikke årlig, slik Base44-workflowen faktisk var satt opp). Kalles også fra
 * frontend (admin). Henter siste 12-måneders KPI fra SSB (tabell 03013), beregner forslag til
 * leiejustering for hver aktiv langtidskontrakt og sender e-post til admin per kontrakt + én
 * samle-SMS-rad (NotificationLog, status pending – originalen sendte ikke SMS).
 *
 * Admin-mottaker: services.reminders.admin_email / admin_phone.
 * Avvik: Core.SendEmail (ren tekst) → Laravel Mail (PlainMail) med teksten i <pre>.
 * Svar: {success, kpi_rate, contracts_processed, results: [{contract_id, property, current_rent, proposed_rent, kpi_rate}]}
 */
class KpiRentAdjustmentReminder extends Base44Function
{
    private const FALLBACK_KPI = 3.5;

    public function __invoke(?User $user, array $payload): array
    {
        if ($user && !$user->hasRole('admin')) {
            throw new FunctionException('Forbidden', 403);
        }

        $adminEmail = config('services.reminders.admin_email');
        $adminPhone = config('services.reminders.admin_phone');
        if (!$adminEmail) {
            throw new FunctionException('REMINDER_ADMIN_EMAIL ikke satt', 500);
        }

        $kpiRate = $this->fetchKpiFromSsb();

        $contracts = Contract::base44Filter(['contract_type' => 'langtid', 'status' => 'aktiv'])->get();
        $tenants = Tenant::all()->keyBy('id');
        $properties = Property::all()->keyBy('id');

        $year = now()->year;
        $results = [];

        foreach ($contracts as $contract) {
            if (!$contract->monthly_rent) {
                continue;
            }
            $property = $properties->get($contract->property_id);
            $tenant = $tenants->get($contract->tenant_id);
            $currentRent = (float) $contract->monthly_rent;
            $newRent = (int) round($currentRent * (1 + $kpiRate / 100));
            $propertyName = $property?->name ?: 'Eiendom';

            $emailBody = $this->buildEmailBody($contract, $property, $tenant, $kpiRate);
            Mail::to($adminEmail)->send(new PlainMail(
                "📊 KPI-justering {$year} – {$propertyName} ({$kpiRate}%)",
                $this->asHtml($emailBody),
            ));

            NotificationLog::create([
                'recipient_email' => $adminEmail,
                'channel' => 'email',
                'subject' => "KPI-justering {$year} – {$propertyName}",
                'body' => $emailBody,
                'status' => 'sent',
                'sent_date' => now(),
                'metadata' => [
                    'contract_id' => $contract->id,
                    'kpi_rate' => $kpiRate,
                    'current_rent' => $currentRent,
                    'proposed_rent' => $newRent,
                    'type' => 'kpi_adjustment',
                ],
            ]);

            $results[] = [
                'contract_id' => $contract->id,
                'property' => $property?->name,
                'current_rent' => $currentRent,
                'proposed_rent' => $newRent,
                'kpi_rate' => $kpiRate,
            ];
        }

        // Én samlet SMS-oppsummering (logges som pending, som i originalen)
        if ($results) {
            $n = count($results);
            NotificationLog::create([
                'recipient_email' => $adminEmail,
                'recipient_phone' => $adminPhone,
                'channel' => 'sms',
                'body' => "KPI-påminnelse ({$kpiRate}%): {$n} kontrakt(er) bør vurderes justert. Forslag sendt på e-post. -Appendix Properties",
                'status' => 'pending',
                'sent_date' => now(),
                'metadata' => ['type' => 'kpi_sms_summary', 'count' => $n, 'kpi_rate' => $kpiRate],
            ]);
        }

        return [
            'success' => true,
            'kpi_rate' => $kpiRate,
            'contracts_processed' => count($results),
            'results' => $results,
        ];
    }

    /** Siste 12-måneders KPI-endring fra SSB (tabell 03013, to siste år). Fallback 3.5. */
    private function fetchKpiFromSsb(): float
    {
        try {
            $res = Http::timeout(20)->asJson()->post('https://data.ssb.no/api/v0/no/table/03013', [
                'query' => [
                    ['code' => 'ContentsCode', 'selection' => ['filter' => 'item', 'values' => ['KpiAlle']]],
                    ['code' => 'Tid', 'selection' => ['filter' => 'top', 'values' => ['2']]],
                ],
                'response' => ['format' => 'json-stat2'],
            ]);
            if (!$res->successful()) {
                return self::FALLBACK_KPI;
            }
            $values = array_values((array) ($res->json('value') ?? []));
            if (count($values) >= 2) {
                $prev = (float) $values[count($values) - 2];
                $curr = (float) $values[count($values) - 1];
                if ($prev > 0) {
                    return round(($curr - $prev) / $prev * 100, 2);
                }
            }
            return self::FALLBACK_KPI;
        } catch (\Throwable) {
            return self::FALLBACK_KPI;
        }
    }

    private function buildEmailBody(Contract $contract, ?Property $property, ?Tenant $tenant, float $kpiRate): string
    {
        $currentRent = (float) ($contract->monthly_rent ?: 0);
        $newRent = (int) round($currentRent * (1 + $kpiRate / 100));
        $increase = $newRent - $currentRent;
        $tenantName = $tenant ? trim("{$tenant->first_name} {$tenant->last_name}") : 'Ukjent';
        $propertyName = $property?->name ?: 'Ukjent eiendom';
        $start = '';
        try { $start = $contract->start_date ? Carbon::parse($contract->start_date)->format('d.m.Y') : ''; } catch (\Throwable) {}
        $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $sign = $kpiRate > 0 ? '+' : '';

        return "Hei,

Dette er en automatisk KPI-justeringspåminnelse fra Appendix Properties.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📋 KONTRAKT: {$propertyName}
👤 Leietaker: {$tenantName}
📅 Kontraktsstart: {$start}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📊 KPI-JUSTERINGSFORSLAG (basert på SSB siste 12 måneder)

Aktuell KPI-endring: {$sign}{$kpiRate}%

  Nåværende månedlig leie:  {$n($currentRent)} kr
  Foreslått ny leie:         {$n($newRent)} kr
  Økning:                    +{$n($increase)} kr/mnd
  Økning per år:             +{$n($increase * 12)} kr/år

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Andre alternativer:
  +2,5% KPI:  {$n(round($currentRent * 1.025))} kr/mnd
  +3,5% KPI:  {$n(round($currentRent * 1.035))} kr/mnd
  +5,0% KPI:  {$n(round($currentRent * 1.05))} kr/mnd

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📌 Neste steg:
1. Logg inn i Appendix Properties
2. Gå til Langtidsutleie → Kontrakter
3. Rediger kontrakten og oppdater månedlig leie
4. Send varsling til leietaker

⚠️ Husk: Leietaker skal varsles skriftlig med minimum 1 måneds varsel
   før leieendringen trer i kraft (Husleieloven § 4-2).

Se aktuell KPI på: https://www.ssb.no/priser-og-prisindekser/konsumpriser/statistikk/konsumprisindeksen

Med vennlig hilsen
Appendix Properties (automatisk KPI-varsling)";
    }

    /** Ren tekst → HTML-kropp for PlainMail. */
    private function asHtml(string $text): string
    {
        return '<pre style="font-family:Arial,sans-serif;font-size:14px;white-space:pre-wrap;color:#1e293b">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '</pre>';
    }
}
