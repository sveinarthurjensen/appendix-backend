<?php

namespace App\Services;

/**
 * Minimal PDF-generator i ren PHP – erstatter npm:jspdf i exportDocumentForSigning.
 * (Ingen PDF-pakke er installert; se rapport for anbefaling om setasign/fpdf eller dompdf.)
 *
 * API-et speiler det lille utsnittet av jsPDF som ble brukt: A4 stående, koordinater i mm
 * fra øverste venstre hjørne, tekst plassert på grunnlinjen (som jsPDF.text), Helvetica
 * normal/bold (standard PDF-fonter, WinAnsi – æøå fungerer), linjer og ett JPEG/PNG-bilde
 * (bilder konverteres til JPEG via GD før innlegging).
 */
class PdfWriter
{
    private const W = 210.0;
    private const H = 297.0;
    private const K = 72 / 25.4; // pt per mm

    /** @var string[] innholdsstrøm per side */
    private array $pages = [];
    private int $page = -1;
    private float $fontSize = 16;
    private string $font = 'F1'; // F1 = Helvetica, F2 = Helvetica-Bold
    /** @var array<int, array{w: int, h: int, data: string}> */
    private array $images = [];

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->page++;
    }

    public function setFontSize(float $size): void
    {
        $this->fontSize = $size;
    }

    /** $style: 'normal' | 'bold' */
    public function setFont(string $family, string $style = 'normal'): void
    {
        $this->font = $style === 'bold' ? 'F2' : 'F1';
    }

    public function text(string $text, float $x, float $y): void
    {
        $enc = $this->winAnsi($text);
        $this->pages[$this->page] .= sprintf(
            "BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $this->font,
            $this->fontSize,
            $x * self::K,
            (self::H - $y) * self::K,
            $this->escape($enc)
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->pages[$this->page] .= sprintf(
            "0.2 w %.2F %.2F m %.2F %.2F l S\n",
            $x1 * self::K,
            (self::H - $y1) * self::K,
            $x2 * self::K,
            (self::H - $y2) * self::K
        );
    }

    /** Legger inn et bilde (PNG/JPEG/GIF/WebP-bytes); $x,$y er øverste venstre hjørne i mm. */
    public function addImage(string $bytes, float $x, float $y, float $w, float $h): void
    {
        if (!function_exists('imagecreatefromstring')) {
            return; // GD mangler – bildet er valgfritt
        }
        $im = @imagecreatefromstring($bytes);
        if (!$im) {
            return;
        }
        // Flat ut gjennomsiktighet mot hvitt, og konverter til JPEG (DCTDecode er enklest å bygge selv)
        $iw = imagesx($im);
        $ih = imagesy($im);
        $flat = imagecreatetruecolor($iw, $ih);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $white);
        imagecopy($flat, $im, 0, 0, 0, 0, $iw, $ih);
        ob_start();
        imagejpeg($flat, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($im);
        imagedestroy($flat);

        $this->images[] = ['w' => $iw, 'h' => $ih, 'data' => $jpeg];
        $idx = count($this->images);
        $this->pages[$this->page] .= sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q\n",
            $w * self::K,
            $h * self::K,
            $x * self::K,
            (self::H - $y - $h) * self::K,
            $idx
        );
    }

    /** Bryter tekst til linjer som får plass innen $maxWidth mm med gjeldende font/størrelse (som jsPDF.splitTextToSize). */
    public function splitTextToSize(string $text, float $maxWidth): array
    {
        $out = [];
        foreach (preg_split("/\r?\n/", $text) as $para) {
            $words = preg_split('/\s+/', trim($para));
            $line = '';
            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                $try = $line === '' ? $word : "$line $word";
                if ($this->textWidth($try) <= $maxWidth) {
                    $line = $try;
                } else {
                    if ($line !== '') {
                        $out[] = $line;
                    }
                    $line = $word;
                }
            }
            $out[] = $line;
        }
        return $out;
    }

    public function textWidth(string $text): float
    {
        $w = 0;
        $enc = $this->winAnsi($text);
        for ($i = 0, $n = strlen($enc); $i < $n; $i++) {
            $w += $this->charWidth(ord($enc[$i]));
        }
        return $w * $this->fontSize / 1000 / self::K;
    }

    /** Tilnærmede Helvetica-bredder (1/1000 em). Bold er litt bredere. */
    private function charWidth(int $c): int
    {
        static $narrow = [32 => 278, 33 => 278, 39 => 191, 40 => 333, 41 => 333, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
            58 => 278, 59 => 278, 73 => 278, 91 => 278, 93 => 278, 102 => 278, 105 => 222, 106 => 222, 108 => 222,
            114 => 333, 116 => 278, 124 => 260];
        static $wide = [64 => 1015, 77 => 833, 87 => 944, 109 => 833, 119 => 722, 37 => 889, 38 => 667, 79 => 778,
            81 => 778, 71 => 778, 67 => 722, 68 => 722, 72 => 722, 78 => 722, 85 => 722, 82 => 722, 75 => 667];
        $w = $narrow[$c] ?? $wide[$c] ?? (($c >= 65 && $c <= 90) ? 667 : 556);
        return $this->font === 'F2' ? (int) round($w * 1.05) : $w;
    }

    private function winAnsi(string $utf8): string
    {
        $s = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $utf8);
        return $s === false ? preg_replace('/[^\x20-\x7E]/', '?', $utf8) : $s;
    }

    private function escape(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '\\r', '\\n'], $s);
    }

    /** Serialiserer hele dokumentet til PDF-bytes. */
    public function output(): string
    {
        $objects = [];
        $add = function (string $body) use (&$objects): int {
            $objects[] = $body;
            return count($objects);
        };

        $fontN = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $fontB = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

        $imageIds = [];
        foreach ($this->images as $i => $img) {
            $imageIds[$i + 1] = $add(sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $img['w'],
                $img['h'],
                strlen($img['data']),
                $img['data']
            ));
        }
        $xobj = '';
        foreach ($imageIds as $n => $id) {
            $xobj .= " /Im$n $id 0 R";
        }
        $resources = "<< /Font << /F1 $fontN 0 R /F2 $fontB 0 R >>" . ($xobj ? " /XObject <<$xobj >>" : '') . ' >>';

        $pagesId = count($objects) + 1 + count($this->pages) * 2; // reserveres etter sidene
        $pageIds = [];
        foreach ($this->pages as $content) {
            $contentId = $add(sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content));
            $pageIds[] = $add(sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>',
                $pagesId,
                self::W * self::K,
                self::H * self::K,
                $resources,
                $contentId
            ));
        }
        $kids = implode(' ', array_map(fn ($id) => "$id 0 R", $pageIds));
        $realPagesId = $add("<< /Type /Pages /Kids [$kids] /Count " . count($pageIds) . ' >>');
        if ($realPagesId !== $pagesId) {
            throw new \LogicException('PDF-objektnummerering ute av synk');
        }
        $catalogId = $add("<< /Type /Catalog /Pages $pagesId 0 R >>");

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root $catalogId 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }
}
