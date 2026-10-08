<?php

namespace App\Jobs;

use App\Functions\BehandleHenvendelse as BehandleHenvendelseFunksjon;
use App\Functions\FunctionException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Portert fra base44/functions/mottaHenvendelse/entry.ts (lagreOgStart) – bakgrunnskallet til
 * behandleHenvendelse. I Base44 gikk dette som et HTTP-kall med 2,5 s tidsfrist; her er det en
 * køjobb (Horizon, standardkøen) slik at nettsiden får kvittering med en gang.
 *
 * Ingen gjentak: BehandleHenvendelse-funksjonen låser saken med taggen «behandles», så et nytt
 * forsøk ville uansett svare 409. Feil logges – nettsiden skal aldri merke dem.
 */
class BehandleHenvendelse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public string $caseId, public array $opts)
    {
    }

    public function handle(): void
    {
        try {
            app(BehandleHenvendelseFunksjon::class)(null, ['case_id' => $this->caseId, 'opts' => $this->opts]);
        } catch (FunctionException $e) {
            // 409 = allerede behandlet / for gammel – ikke en feil for køen.
            Log::warning('BehandleHenvendelse-jobb: ' . $e->getMessage() . ' (' . $e->status . ') sak ' . $this->caseId);
        } catch (\Throwable $e) {
            Log::error('BehandleHenvendelse-jobb feilet for sak ' . $this->caseId . ': ' . Str::limit($e->getMessage(), 300, ''));
        }
    }
}
