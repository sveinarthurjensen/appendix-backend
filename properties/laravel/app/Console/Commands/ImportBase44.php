<?php

namespace App\Console\Commands;

use App\Support\EntityRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan base44:import <mappe-med-json>  [--entity=Property] [--dry-run]
 *
 * Forventer én fil per entitet: <mappe>/<Entity>.json med en liste av rader
 * slik query_entities returnerer dem. ID-ene beholdes. Kjøres idempotent (upsert).
 * Skriver avstemming: antall i fil vs. antall i tabell etterpå.
 */
class ImportBase44 extends Command
{
    protected $signature = 'base44:import {dir} {--entity=} {--dry-run}';
    protected $description = 'Importer JSON-eksport fra Base44 inn i Laravel-tabellene (ID bevares)';

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        $only = $this->option('entity');
        $dry = $this->option('dry-run');
        $summary = [];

        foreach (EntityRegistry::MODELS as $entity => $class) {
            if ($only && $only !== $entity) continue;
            $file = "$dir/$entity.json";
            if (!is_file($file)) { $summary[] = [$entity, '-', 'ingen fil']; continue; }

            $rows = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $model = new $class();
            $fillable = array_flip($model->getFillable());
            $casts = $model->getCasts();
            $n = 0; $skippedFields = [];

            DB::transaction(function () use ($rows, $class, $fillable, $casts, $model, $dry, &$n, &$skippedFields) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    $batch = [];
                    foreach ($chunk as $r) {
                        $row = [
                            'id' => $r['id'],
                            'app_id' => $class::APP_ID,
                            'created_by' => $r['created_by'] ?? null,
                            'created_date' => $r['created_date'] ?? null,
                            'updated_date' => $r['updated_date'] ?? null,
                            'is_sample' => (bool) ($r['is_sample'] ?? false),
                        ];
                        foreach ($r as $k => $v) {
                            if (isset($row[$k])) continue;
                            if (!isset($fillable[$k])) { $skippedFields[$k] = true; continue; }
                            if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                            elseif (($casts[$k] ?? null) === 'float' && $v === '') $v = null;
                            $row[$k] = $v;
                        }
                        $batch[] = $row;
                        $n++;
                    }
                    if (!$dry && $batch) {
                        // Alle rader må ha samme kolonner (Base44 utelater tomme felt) → fyll ut med null
                        $cols = [];
                        foreach ($batch as $b) {
                            foreach (array_keys($b) as $c) {
                                $cols[$c] = true;
                            }
                        }
                        $batch = array_map(fn ($b) => array_merge(array_fill_keys(array_keys($cols), null), $b), $batch);
                        DB::table($model->getTable())->upsert($batch, ['id'], array_keys($cols));
                    }
                }
            });

            $inDb = $dry ? '-' : $class::withoutGlobalScopes()->count();
            $summary[] = [$entity, count($rows), $inDb, $skippedFields ? 'ukjente felt: ' . implode(',', array_keys($skippedFields)) : ''];
        }

        $this->table(['Entitet', 'I fil', 'I tabell', 'Merknad'], $summary);
        return self::SUCCESS;
    }
}
