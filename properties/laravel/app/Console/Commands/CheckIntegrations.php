<?php

namespace App\Console\Commands;

use App\Services\MicrosoftGraph;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/** php artisan app:check-integrations – tester at nøklene i .env faktisk virker, uten å vise dem. */
class CheckIntegrations extends Command
{
    protected $signature = 'app:check-integrations {--mailbox=post@aprop.no} {--sms= : Send en test-SMS til dette nummeret (koster én SMS)}';
    protected $description = 'Sjekk Graph, Sveve, Anthropic, Google Maps, SMTP-konfig';

    public function handle(): int
    {
        $rows = [];
        $rows[] = ['Microsoft Graph', $this->try(function () {
            $r = app(MicrosoftGraph::class)->get('/users/' . rawurlencode($this->option('mailbox')) . '/mailFolders?$top=1');
            return 'OK – leser ' . $this->option('mailbox');
        })];
        $rows[] = ['Sveve SMS', config('services.sveve.username') ? $this->try(function () {
            $r = Http::asForm()->timeout(15)->post('https://sveve.no/SMS/AccountAdm', [
                'cmd' => 'sms_count', 'user' => config('services.sveve.username'), 'passwd' => config('services.sveve.password'),
            ]);
            return is_numeric(trim($r->body())) ? 'OK – ' . trim($r->body()) . ' SMS igjen' : 'HTTP ' . $r->status() . ', svar: «' . substr(trim($r->body()), 0, 80) . '» (brukernavn ' . strlen((string) config('services.sveve.username')) . ' tegn, passord ' . strlen((string) config('services.sveve.password')) . ' tegn)';
        }) : 'mangler nøkkel'];
        $rows[] = ['Anthropic', config('services.anthropic.key') ? $this->try(fn () => 'OK – ' . trim(app(\App\Services\Llm::class)->text('Svar med ordet OK', 5))) : 'mangler nøkkel'];
        $rows[] = ['Google Maps', config('services.google_maps.key') ? $this->try(function () {
            $r = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/streetview/metadata', ['location' => 'Oslo', 'key' => config('services.google_maps.key')]);
            return ($r->json('status') ?? '?') === 'OK' ? 'OK' : 'Status: ' . $r->json('status') . ' – ' . substr((string) $r->json('error_message'), 0, 140);
        }) : 'mangler nøkkel'];
        $rows[] = ['SMTP', config('mail.mailers.smtp.username') ? 'konfigurert (' . config('mail.mailers.smtp.host') . ')' : 'mangler MAIL_USERNAME/PASSWORD'];
        if ($to = $this->option('sms')) {
            $r = app(\App\Services\SveveSms::class)->send($to, 'Test fra Appendix-backend ' . now()->format('H:i'));
            $rows[] = ['Sveve test-SMS til ' . $to, $r['ok'] ? 'SENDT (' . ($r['msgOkCount'] ?? '?') . ')' : 'FEIL: ' . ($r['error'] ?? '?') . ' ' . substr(json_encode($r['raw'] ?? null), 0, 160)];
        }
        $this->table(['Tjeneste', 'Resultat'], $rows);
        return self::SUCCESS;
    }

    private function try(callable $f): string
    {
        try { return $f(); } catch (\Throwable $e) { return 'FEIL: ' . substr($e->getMessage(), 0, 160); }
    }
}
