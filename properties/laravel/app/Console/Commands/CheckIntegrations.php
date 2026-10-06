<?php

namespace App\Console\Commands;

use App\Services\MicrosoftGraph;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/** php artisan app:check-integrations – tester at nøklene i .env faktisk virker, uten å vise dem. */
class CheckIntegrations extends Command
{
    protected $signature = 'app:check-integrations {--mailbox=post@aprop.no}';
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
            return is_numeric(trim($r->body())) ? 'OK – ' . trim($r->body()) . ' SMS igjen' : 'Svar: ' . substr($r->body(), 0, 80);
        }) : 'mangler nøkkel'];
        $rows[] = ['Anthropic', config('services.anthropic.key') ? $this->try(fn () => 'OK – ' . trim(app(\App\Services\Llm::class)->text('Svar med ordet OK', 5))) : 'mangler nøkkel'];
        $rows[] = ['Google Maps', config('services.google_maps.key') ? $this->try(function () {
            $r = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/streetview/metadata', ['location' => 'Oslo', 'key' => config('services.google_maps.key')]);
            return ($r->json('status') ?? '?') === 'OK' ? 'OK' : 'Status: ' . $r->json('status');
        }) : 'mangler nøkkel'];
        $rows[] = ['SMTP', config('mail.mailers.smtp.username') ? 'konfigurert (' . config('mail.mailers.smtp.host') . ')' : 'mangler MAIL_USERNAME/PASSWORD'];
        $this->table(['Tjeneste', 'Resultat'], $rows);
        return self::SUCCESS;
    }

    private function try(callable $f): string
    {
        try { return $f(); } catch (\Throwable $e) { return 'FEIL: ' . substr($e->getMessage(), 0, 160); }
    }
}
