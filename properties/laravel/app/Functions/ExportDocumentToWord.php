<?php

namespace App\Functions;

use App\Models\User;
use App\Services\DocxWriter;

/**
 * Portert fra base44/functions/exportDocumentToWord/entry.ts
 *
 * Bygger et enkelt brev som .docx og returnerer det base64-kodet.
 * Svar: {base64, filename, mime}
 *
 * Valg: originalen brukte npm:docx og laget ekte OOXML. phpoffice/phpword er ikke installert,
 * så DocxWriter (app/Services) lager en minimal, gyldig .docx via ZipArchive med document.xml
 * + styles.xml. Det gir samme filtype/MIME som før (ikke HTML-til-.doc), og Word/LibreOffice åpner den.
 */
class ExportDocumentToWord extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $user = $this->requireUser($user);

        $title = $payload['title'] ?? null;
        $subject = $payload['subject'] ?? null;
        $recipientName = $payload['recipient_name'] ?? null;
        $recipientAddress = $payload['recipient_address'] ?? null;
        $letterDate = $payload['letter_date'] ?? null;
        $senderName = $payload['sender_name'] ?? null;
        $senderTitle = $payload['sender_title'] ?? null;
        $body = $payload['body'] ?? '';

        $plainBody = str_replace(['&nbsp;', '&amp;'], [' ', '&'], preg_replace('/<[^>]+>/', "\n", (string) $body));
        $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $plainBody)), fn ($p) => $p !== ''));

        $w = new DocxWriter();
        $w->paragraph('Appendix Properties', ['bold' => true, 'size' => 32, 'align' => 'left']);
        $w->paragraph($letterDate ?: now()->utc()->format('Y-m-d'), ['italic' => true]);
        $w->paragraph(' ');
        if ($recipientName) {
            $w->paragraph($recipientName);
        }
        if ($recipientAddress) {
            foreach (explode("\n", $recipientAddress) as $l) {
                $w->paragraph($l);
            }
        }
        $w->paragraph(' ');
        $w->heading($subject ?: ($title ?: ''), 2);
        $w->paragraph(' ');
        foreach ($paragraphs as $p) {
            $w->paragraph($p);
        }
        $w->paragraph(' ');
        $w->paragraph('Med vennlig hilsen,');
        $w->paragraph(' ');
        $w->paragraph($senderName ?: (string) $user->full_name, ['bold' => true]);
        if ($senderTitle) {
            $w->paragraph($senderTitle);
        }

        try {
            $bytes = $w->toBytes();
        } catch (\Throwable $e) {
            report($e);
            throw new FunctionException($e->getMessage(), 500);
        }

        return [
            'base64' => base64_encode($bytes),
            'filename' => preg_replace('/[^a-z0-9æøåA-ZÆØÅ]/u', '_', $title ?: 'brev') . '.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }
}
