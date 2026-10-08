<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GET /api/_diag?token=<DIAG_TOKEN>
 *
 * Driftsdiagnose uten serverinnlogging. Viser ALDRI hemmeligheter – bare om de er satt.
 * Loggutdrag: kun feilmeldingslinjer (uten stacktrace), avkortet. Skal fjernes eller strammes inn
 * (IP-begrensning) før helseopplysninger lagres i systemet – se sjekkliste A7.
 */
class DiagController extends Controller
{
    private const SECRET_KEYS = [
        'AZURE_TENANT_ID', 'AZURE_CLIENT_ID', 'AZURE_CLIENT_SECRET',
        'ENTRA_LOGIN_CLIENT_ID', 'ENTRA_LOGIN_CLIENT_SECRET',
        'SVEVE_USERNAME', 'SVEVE_PASSWORD', 'ANTHROPIC_API_KEY', 'GOOGLE_MAPS_API_KEY',
        'MAIL_USERNAME', 'MAIL_PASSWORD', 'SIGNICAT_CLIENT_ID', 'SIGNICAT_CLIENT_SECRET',
        'ONEDRIVE_USER_EMAIL', 'BACKUP_ENCRYPTION_KEY', 'RECEIVE_LOCATIONS_TOKEN', 'ARBEIDSFLATE_NOKKEL',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        // env() virker ikke når config er cachet – les .env direkte (filen er montert i containeren)
        $envFile = $this->readEnv();
        $expected = (string) ($envFile['DIAG_TOKEN'] ?? '');
        abort_if($expected === '' || !hash_equals($expected, (string) $request->query('token', '')), 404);

        $deploy = @json_decode((string) @file_get_contents('/deploy/status.json'), true) ?: null;
        $deployLog = @file_get_contents('/deploy/last-deploy.log');

        $keys = [];
        foreach (self::SECRET_KEYS as $k) {
            $v = (string) ($envFile[$k] ?? '');
            $keys[$k] = $v === '' ? 'mangler' : (preg_match('/\[20[01]~|\s/', $v) ? 'SATT, MEN UGYLDIG (mellomrom/innlimingskoder)' : 'satt (' . strlen($v) . ' tegn)');
        }

        return response()->json([
            'deploy' => $deploy,
            'deploy_log_tail' => $deployLog ? $this->tail($deployLog, 40) : null,
            'app' => [
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
                'key_ok' => str_starts_with((string) config('app.key'), 'base64:'),
                'config_cached' => app()->configurationIsCached(),
                'routes_cached' => app()->routesAreCached(),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'oidc' => [
                'issuer' => config('services.oidc.issuer'),
                'clients' => array_keys((array) config('services.oidc.clients')),
                'entra_redirect_uri' => config('services.entra_login.redirect_uri'),
                'route_entra_callback' => route('auth.entra.callback'),
                'entra_tenant' => config('services.entra_login.tenant'),
            ],
            'keys' => $keys,
            'database' => $this->db(),
            'queue' => ['connection' => config('queue.default')],
            'log_errors' => $this->logErrors(),
            'time' => now()->toIso8601String(),
        ]);
    }

    /** @return array<string,string> */
    private function readEnv(): array
    {
        $out = [];
        foreach (@file(base_path('.env'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
                $out[$m[1]] = trim($m[2], "\"'");
            }
        }
        return $out;
    }

    private function db(): array
    {
        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')));
            return [
                'ok' => true,
                'migrations_ran' => count($ran),
                'migrations_pending' => array_values(array_diff($files, $ran)),
                'users' => Schema::hasTable('users') ? DB::table('users')->count() : null,
                'properties' => Schema::hasTable('properties') ? DB::table('properties')->count() : null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => substr($e->getMessage(), 0, 300)];
        }
    }

    private function logErrors(): array
    {
        $file = storage_path('logs/laravel.log');
        if (!is_file($file)) return [];
        $fh = fopen($file, 'r');
        $size = filesize($file);
        fseek($fh, max(0, $size - 200_000));
        $chunk = stream_get_contents($fh);
        fclose($fh);
        preg_match_all('/^\[(\d{4}-\d\d-\d\d[^\]]*)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY|WARNING): (.*)$/m', $chunk, $m, PREG_SET_ORDER);
        $out = [];
        foreach (array_slice($m, -15) as $row) {
            $out[] = $row[1] . ' ' . $row[2] . ': ' . substr($row[3], 0, 400);
        }
        return $out;
    }

    private function tail(string $s, int $n): array
    {
        $lines = preg_split('/\R/', trim($s));
        $lines = array_map(fn ($l) => preg_replace('/\e\[[\d;]*m/', '', $l), $lines);
        return array_slice($lines, -$n);
    }
}
