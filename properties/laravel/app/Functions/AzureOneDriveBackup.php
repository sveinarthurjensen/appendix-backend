<?php

namespace App\Functions;

use App\Models\DataBackup;
use App\Models\User;
use App\Services\MicrosoftGraph;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/azureOneDriveBackup/entry.ts
 *
 * Planlagt jobb (workflow «Nattlig Azure OneDrive Backup», cron 0 2 * * * UTC).
 *
 * AVVIK: Originalen eksporterte 12 entiteter til JSON via Base44-SDK-en og lastet opp den.
 * I Laravel tas databasen allerede backup av (pgbackup-containeren skriver pg_dump-filer til
 * /backups). Denne jobben laster derfor opp den NYESTE dump-filen i services.backup.dir
 * (standard /backups) til samme OneDrive-mappe som før:
 *   /users/{ONEDRIVE_USER_EMAIL}/drive/root:/Backups/AppendixProperties/{filnavn}
 * Filer > 4 MB lastes opp via Graph upload session (chunket); mindre via enkel PUT.
 *
 * docker-compose: app- og scheduler-containeren må montere samme volum som pgbackup skriver
 * til, f.eks.  volumes: - pgbackup-data:/backups:ro
 *
 * Svar: {success, file_name, records (alltid 0 – ingen entitetseksport), file_size, web_url}.
 * DataBackup-rad skrives med backup_type 'onedrive' som før.
 */
class AzureOneDriveBackup extends Base44Function
{
    private const CHUNK = 10 * 1024 * 1024; // 10 MB, multiplum av 320 KiB som Graph krever
    private const SIMPLE_PUT_MAX = 4 * 1024 * 1024;

    public function __invoke(?User $user, array $payload): array
    {
        if ($user && !$user->hasRole('admin')) {
            throw new FunctionException('Kun admin', 403);
        }

        $userEmail = config('services.graph.onedrive_user');
        if (!config('services.graph.tenant') || !config('services.graph.client_id') || !config('services.graph.client_secret') || !$userEmail) {
            throw new FunctionException('Azure-konfigurasjon mangler. Sett AZURE_TENANT_ID, AZURE_CLIENT_ID, AZURE_CLIENT_SECRET og ONEDRIVE_USER_EMAIL.', 500);
        }

        $dir = rtrim((string) config('services.backup.dir', '/backups'), '/');
        $folder = trim((string) config('services.backup.onedrive_folder', 'Backups/AppendixProperties'), '/');

        $file = $this->newestDump($dir);
        if (!$file) {
            $this->logFailure(null, "Ingen backup-fil funnet i {$dir}");
            throw new FunctionException("Ingen backup-fil funnet i {$dir}", 500);
        }

        $fileName = basename($file);
        $fileSize = (int) filesize($file);
        $graph = app(MicrosoftGraph::class);
        $itemPath = "/users/{$userEmail}/drive/root:/{$folder}/" . rawurlencode($fileName);

        try {
            if ($fileSize <= self::SIMPLE_PUT_MAX) {
                $res = Http::withToken($graph->token())
                    ->timeout(120)
                    ->withBody((string) file_get_contents($file), 'application/octet-stream')
                    ->put(MicrosoftGraph::BASE . $itemPath . ':/content');
                if (!$res->successful()) {
                    throw new FunctionException('OneDrive opplasting feilet: ' . $res->body(), 500);
                }
                $uploadResult = $res->json() ?? [];
            } else {
                $uploadResult = $this->uploadLarge($graph, $itemPath, $file, $fileSize);
            }
        } catch (\Throwable $e) {
            $this->logFailure($fileName, $e->getMessage(), $fileSize);
            throw $e instanceof FunctionException ? $e : new FunctionException('OneDrive opplasting feilet: ' . $e->getMessage(), 500);
        }

        $webUrl = $uploadResult['webUrl'] ?? null;

        DataBackup::create([
            'backup_date' => now(),
            'backup_type' => 'onedrive',
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'entity_count' => 0,
            'record_count' => 0,
            'status' => 'success',
            'destination_results' => ['onedrive' => $webUrl],
            'created_by_name' => $user?->full_name ?: 'scheduler',
        ]);

        return [
            'success' => true,
            'file_name' => $fileName,
            'records' => 0,
            'file_size' => $fileSize,
            'web_url' => $webUrl,
        ];
    }

    /** Nyeste fil (etter mtime) i katalogen – pg_dump-filer: *.sql, *.sql.gz, *.dump, *.backup, *.tar(.gz). */
    private function newestDump(string $dir): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }
        $files = glob($dir . '/*') ?: [];
        $files = array_filter($files, fn ($f) => is_file($f) && preg_match('/\.(sql|sql\.gz|dump|backup|pgdump|tar|tar\.gz|tgz|gz)$/i', $f));
        if (!$files) {
            return null;
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return $files[0];
    }

    /** Graph upload session: createUploadSession + chunkede PUT-er mot den forhåndsautoriserte uploadUrl. */
    private function uploadLarge(MicrosoftGraph $graph, string $itemPath, string $file, int $fileSize): array
    {
        $session = $graph->post($itemPath . ':/createUploadSession', [
            'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
        ]);
        $uploadUrl = $session['uploadUrl'] ?? null;
        if (!$uploadUrl) {
            throw new FunctionException('OneDrive: fikk ingen uploadUrl fra createUploadSession', 500);
        }

        $fh = fopen($file, 'rb');
        if (!$fh) {
            throw new FunctionException("Kan ikke lese {$file}", 500);
        }
        $result = [];
        try {
            $offset = 0;
            while ($offset < $fileSize) {
                $chunk = fread($fh, self::CHUNK);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $len = strlen($chunk);
                $end = $offset + $len - 1;
                $res = Http::timeout(300)
                    ->withHeaders([
                        'Content-Length' => (string) $len,
                        'Content-Range' => "bytes {$offset}-{$end}/{$fileSize}",
                    ])
                    ->withBody($chunk, 'application/octet-stream')
                    ->put($uploadUrl);
                if (!$res->successful()) {
                    throw new FunctionException("OneDrive opplasting feilet (chunk {$offset}-{$end}): " . $res->body(), 500);
                }
                $result = $res->json() ?? [];
                $offset += $len;
            }
        } finally {
            fclose($fh);
        }
        return $result;
    }

    private function logFailure(?string $fileName, string $error, int $fileSize = 0): void
    {
        try {
            DataBackup::create([
                'backup_date' => now(),
                'backup_type' => 'onedrive',
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'entity_count' => 0,
                'record_count' => 0,
                'status' => 'failed',
                'error_message' => mb_substr($error, 0, 2000),
            ]);
        } catch (\Throwable) {}
    }
}
