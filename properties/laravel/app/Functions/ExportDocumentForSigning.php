<?php

namespace App\Functions;

use App\Models\User;
use App\Services\PdfWriter;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/exportDocumentForSigning/entry.ts
 *
 * Bygger brevet som PDF (logo, dato, mottaker, emne, brødtekst, hilsen, ev. signaturlinje)
 * og returnerer det base64-kodet. Svar: {base64, filename, mime}
 *
 * Originalen brukte npm:jspdf. Ingen PDF-pakke er installert, så PdfWriter (app/Services) er
 * en liten ren-PHP-generator med samme layout (A4, mm-koordinater, Helvetica 10/14 pt).
 * Konfig: services.letters.logo_url (standard = samme Base44-media-URL som originalen; bør
 * byttes til egen S3-URL når mediefilene flyttes).
 */
class ExportDocumentForSigning extends Base44Function
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
        $includeSignatureLine = $payload['include_signature_line'] ?? null;

        try {
            $doc = new PdfWriter();

            $y = 18;
            $logoUrl = config('services.letters.logo_url');
            if ($logoUrl) {
                try {
                    $img = Http::timeout(10)->get($logoUrl);
                    if ($img->successful()) {
                        $doc->addImage($img->body(), 15, 12, 55, 16);
                        $y = 36;
                    }
                } catch (\Throwable) {
                    // logo valgfritt
                }
            }

            $doc->setFontSize(10);
            $doc->setFont('helvetica', 'normal');
            $doc->text($letterDate ?: now()->utc()->format('Y-m-d'), 15, $y);
            $y += 10;
            if ($recipientName) {
                $doc->text($recipientName, 15, $y);
                $y += 6;
            }
            if ($recipientAddress) {
                foreach (explode("\n", $recipientAddress) as $l) {
                    $doc->text($l, 15, $y);
                    $y += 6;
                }
            }
            $y += 6;
            $doc->setFontSize(14);
            $doc->setFont('helvetica', 'bold');
            $doc->text($subject ?: ($title ?: ''), 15, $y);
            $y += 10;
            $doc->setFontSize(10);
            $doc->setFont('helvetica', 'normal');

            $plainBody = str_replace(['&nbsp;', '&amp;'], [' ', '&'], preg_replace('/<[^>]+>/', "\n", (string) $body));
            foreach ($doc->splitTextToSize($plainBody, 180) as $l) {
                if ($y > 270) {
                    $doc->addPage();
                    $y = 20;
                }
                $doc->text($l, 15, $y);
                $y += 6;
            }
            $y += 10;
            $doc->text('Med vennlig hilsen,', 15, $y);
            $y += 12;
            $doc->setFont('helvetica', 'bold');
            $doc->text($senderName ?: ((string) $user->full_name ?: ''), 15, $y);
            $y += 6;
            $doc->setFont('helvetica', 'normal');
            $doc->text($senderTitle ?: '', 15, $y);
            $y += 16;

            if ($includeSignatureLine !== false) {
                $doc->line(15, $y, 95, $y);
                $doc->text('Signatur', 15, $y + 6);
            }

            $pdf = $doc->output();
        } catch (\Throwable $e) {
            report($e);
            throw new FunctionException($e->getMessage(), 500);
        }

        return [
            'base64' => base64_encode($pdf),
            'filename' => preg_replace('/[^a-z0-9æøåA-ZÆØÅ]/u', '_', $title ?: 'brev') . '.pdf',
            'mime' => 'application/pdf',
        ];
    }
}
