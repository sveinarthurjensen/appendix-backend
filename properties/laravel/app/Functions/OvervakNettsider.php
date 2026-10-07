<?php

namespace App\Functions;

use App\Models\NettsideStatus;
use App\Models\User;
use App\Services\PortalThread;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/overvakNettsider/entry.ts
 *
 * Overvåking av nettsidene på egen server (46.251.249.117) og portalene. Kjøres hvert 5. minutt
 * (workflow «Overvåk nettsidene»). Varsler VARSEL_MOTTAKERE (Svein og Charlotte) på e-post + SMS
 * når en side har feilet GRENSE ganger på rad, og igjen når den er oppe. Ingen gjentatte varsler
 * mens den er nede.
 *
 * Feil = ikke 2xx etter omdirigering, tidsavbrudd (15 s), SSL-feil, eller WordPress-feilside
 * («kritisk feil» / «Error establishing a database connection»).
 * Svar: {sjekket: [{navn, ok, status, ms, feil}], varslet_nede, varslet_oppe}
 */
class OvervakNettsider extends Base44Function
{
    private const SIDER = [
        ['navn' => 'Sørlandet Privatsykehus', 'url' => 'https://spsh.no/'],
        ['navn' => 'Estetisk Plastikkirurgi', 'url' => 'https://estetiskplastikkirurgi.no/'],
        ['navn' => 'epkir.no (omdirigering)', 'url' => 'https://epkir.no/'],
        ['navn' => 'Foreningsdomstolen', 'url' => 'https://foreningsdomstolen.no/'],
        ['navn' => 'Kommuneoverlegene', 'url' => 'https://komoverlege.no/'],
        ['navn' => 'Appendix Properties', 'url' => 'https://aprop.no/'],
        ['navn' => 'Geilo Lodge', 'url' => 'https://geilolodge.com/'],
        ['navn' => 'aprop-portalen', 'url' => 'https://portal.aprop.no/'],
        ['navn' => 'Foreningsdomstolen-portalen', 'url' => 'https://portal.foreningsdomstolen.no/'],
        ['navn' => 'Kommuneoverlege-portalen', 'url' => 'https://portal.komoverlege.no/'],
    ];

    private const FEILTEKST = '/(kritisk feil på dette nettstedet|critical error on this website|Error establishing a database connection|502 Bad Gateway|503 Service Unavailable)/iu';
    private const GRENSE = 2;
    private const TIMEOUT = 15;

    public function __invoke(?User $user, array $payload): array
    {
        // Kun admin eller planlagt kjøring (ingen bruker).
        if ($user && !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin', 403);
        }

        $eksisterende = NettsideStatus::all();
        $naa = now();

        // Promise.all → Http::pool (parallelle kall).
        $t0 = microtime(true);
        $responses = Http::pool(function (Pool $pool) {
            foreach (self::SIDER as $i => $s) {
                $pool->as((string) $i)
                    ->timeout(self::TIMEOUT)
                    ->connectTimeout(self::TIMEOUT)
                    ->withUserAgent('AppendixOvervaking/1.0')
                    ->withHeaders(['Cache-Control' => 'no-cache'])
                    ->get($s['url']);
            }
        });
        $totalMs = (int) round((microtime(true) - $t0) * 1000);

        $resultater = [];
        foreach (self::SIDER as $i => $s) {
            $resultater[] = ['s' => $s, 'r' => $this->vurder($responses[(string) $i] ?? null, $totalMs)];
        }

        $nede = [];
        $oppe = [];
        foreach ($resultater as ['s' => $s, 'r' => $r]) {
            $rad = $eksisterende->firstWhere('url', $s['url'])
                ?? NettsideStatus::create(['url' => $s['url'], 'navn' => $s['navn'], 'ok' => true, 'feil_paa_rad' => 0, 'varslet_nede' => false]);

            $patch = ['navn' => $s['navn'], 'sist_sjekket' => $naa, 'siste_status' => $r['status'], 'svartid_ms' => $r['ms']];
            if ($r['ok']) {
                if ($rad->varslet_nede) {
                    $oppe[] = "{$s['navn']} ({$s['url']}) er oppe igjen – nede siden " . ($rad->nede_siden ? $this->tid($rad->nede_siden) : '?');
                }
                $patch += ['ok' => true, 'feil_paa_rad' => 0, 'varslet_nede' => false, 'siste_feil' => '', 'nede_siden' => null];
            } else {
                $antall = (int) ($rad->feil_paa_rad ?: 0) + 1;
                $patch += ['ok' => false, 'feil_paa_rad' => $antall, 'siste_feil' => $r['feil']];
                if (!$rad->nede_siden) {
                    $patch['nede_siden'] = $naa;
                }
                if ($antall >= self::GRENSE && !$rad->varslet_nede) {
                    $nede[] = "{$s['navn']} ({$s['url']}): {$r['feil']}";
                    $patch['varslet_nede'] = true;
                }
            }
            try { $rad->update($patch); } catch (\Throwable) {}
        }

        $e = fn ($v) => PortalThread::escapeHtml($v);
        if ($nede) {
            $n = count($nede);
            PortalThread::sendStaffAlert(
                "NEDE: {$n} nettside" . ($n > 1 ? 'r' : ''),
                '<p>Disse sidene svarer ikke (sjekket ' . self::GRENSE . ' ganger på rad, ' . $this->tid($naa) . '):</p><ul>'
                    . implode('', array_map(fn ($x) => '<li>' . $e($x) . '</li>', $nede))
                    . '</ul><p>Server: 46.251.249.117. Varsel om at siden er oppe igjen kommer automatisk.</p>',
                mb_substr('NEDE: ' . implode('; ', $nede), 0, 450),
            );
        }
        if ($oppe) {
            $n = count($oppe);
            PortalThread::sendStaffAlert(
                "OPPE igjen: {$n} nettside" . ($n > 1 ? 'r' : ''),
                '<ul>' . implode('', array_map(fn ($x) => '<li>' . $e($x) . '</li>', $oppe)) . '</ul>',
                mb_substr('OPPE igjen: ' . implode('; ', $oppe), 0, 450),
            );
        }

        return [
            'sjekket' => array_map(fn ($x) => [
                'navn' => $x['s']['navn'], 'ok' => $x['r']['ok'], 'status' => $x['r']['status'], 'ms' => $x['r']['ms'], 'feil' => $x['r']['feil'],
            ], $resultater),
            'varslet_nede' => count($nede),
            'varslet_oppe' => count($oppe),
        ];
    }

    /** @return array{ok: bool, status: int, ms: int, feil: string} */
    private function vurder(mixed $res, int $totalMs): array
    {
        if (!$res instanceof Response) {
            // ConnectionException (tidsavbrudd, DNS, SSL) eller annen Throwable fra poolen.
            $msg = $res instanceof \Throwable ? ($res->getMessage() ?: get_class($res)) : 'Ukjent feil';
            return ['ok' => false, 'status' => 0, 'ms' => $totalMs, 'feil' => mb_substr($msg, 0, 200)];
        }
        // Svartid per side er ikke tilgjengelig i poolen; Guzzle rapporterer total_time når den finnes.
        $ms = $totalMs;
        try {
            $stats = $res->handlerStats();
            if (isset($stats['total_time'])) {
                $ms = (int) round($stats['total_time'] * 1000);
            }
        } catch (\Throwable) {}

        $tekst = mb_substr((string) $res->body(), 0, 200000);
        if (!$res->successful()) {
            return ['ok' => false, 'status' => $res->status(), 'ms' => $ms, 'feil' => "HTTP {$res->status()}"];
        }
        if (preg_match(self::FEILTEKST, $tekst)) {
            return ['ok' => false, 'status' => $res->status(), 'ms' => $ms, 'feil' => 'Feilside i innholdet'];
        }
        if (strlen($tekst) < 200) {
            return ['ok' => false, 'status' => $res->status(), 'ms' => $ms, 'feil' => 'Tom side'];
        }
        return ['ok' => true, 'status' => $res->status(), 'ms' => $ms, 'feil' => ''];
    }

    private function tid(mixed $iso): string
    {
        try {
            return Carbon::parse($iso)->setTimezone('Europe/Oslo')->format('d.m.Y, H:i');
        } catch (\Throwable) {
            return (string) $iso;
        }
    }
}
