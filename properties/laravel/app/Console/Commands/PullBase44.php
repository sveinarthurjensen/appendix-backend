<?php

namespace App\Console\Commands;

use App\Support\EntityRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * php artisan base44:pull [--entity=Property] [--keep]
 *
 * Henter alle rader direkte fra Base44 (GET /api/apps/{app}/entities/{Entity}/v2/list, cursor-paginert,
 * skrivebeskyttet) til en midlertidig mappe på serveren og kjører deretter base44:import.
 * Dataene går aldri via chat eller repo. Mappen slettes etterpå (bruk --keep for å beholde den).
 *
 * Krever BASE44_API_KEY (personlig API-nøkkel, skrivebeskyttet holder) i .env; BASE44_APP_ID har standard
 * Appendix Properties. Nøkkelen kan fjernes fra .env når flyttingen er ferdig.
 */
class PullBase44 extends Command
{
    protected $signature = 'base44:pull {--entity=} {--keep} {--dry-run}';
    protected $description = 'Hent data fra Base44 (read-only) og importer i Laravel-tabellene';

    public function handle(): int
    {
        $key = (string) config('services.base44.api_key');
        $app = (string) config('services.base44.app_id');
        $api = rtrim((string) config('services.base44.api_url'), '/');
        if ($key === '' || $app === '') {
            $this->error('BASE44_API_KEY (og BASE44_APP_ID) mangler i .env');
            return self::FAILURE;
        }

        $dir = storage_path('app/base44-export-' . date('Ymd-His'));
        mkdir($dir, 0700, true);
        $only = $this->option('entity');
        $report = [];

        foreach (array_keys(EntityRegistry::MODELS) as $entity) {
            if ($only && $only !== $entity) {
                continue;
            }
            try {
                $rows = $this->fetchAll($api, $app, $key, $entity);
            } catch (\Throwable $e) {
                $report[] = [$entity, 'FEIL', mb_substr($e->getMessage(), 0, 80)];
                continue;
            }
            file_put_contents("$dir/$entity.json", json_encode($rows, JSON_UNESCAPED_UNICODE));
            $report[] = [$entity, count($rows), ''];
        }
        $this->table(['Entitet', 'Hentet', 'Merknad'], $report);

        $code = $this->call('base44:import', array_filter([
            'dir' => $dir,
            '--entity' => $only,
            '--dry-run' => $this->option('dry-run') ?: null,
        ]));

        if (!$this->option('keep')) {
            foreach (glob("$dir/*.json") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        } else {
            $this->warn("Eksporten ligger i $dir – slett den når du er ferdig (inneholder persondata).");
        }
        return $code;
    }

    private function fetchAll(string $api, string $app, string $key, string $entity): array
    {
        $rows = [];
        $cursor = null;
        do {
            $query = $cursor ? ['cursor' => $cursor] : ['limit' => 500, 'sort' => 'created_date'];
            $res = Http::withHeaders(['api_key' => $key, 'Accept' => 'application/json'])
                ->timeout(60)->retry(2, 500)
                ->get("$api/apps/$app/entities/$entity/v2/list", $query);
            if (!$res->ok()) {
                throw new \RuntimeException('HTTP ' . $res->status() . ' ' . mb_substr($res->body(), 0, 120));
            }
            foreach ((array) $res->json('items', []) as $r) {
                if (is_array($r)) {
                    $rows[] = $r;
                }
            }
            $cursor = $res->json('has_more') ? $res->json('next_cursor') : null;
        } while ($cursor);
        return $rows;
    }
}
