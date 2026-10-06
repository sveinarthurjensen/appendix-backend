<?php

namespace App\Services;

/**
 * Minimal .docx-generator i ren PHP (ZipArchive + WordprocessingML).
 * Erstatter npm:docx i exportDocumentToWord – phpoffice/phpword er ikke installert.
 *
 * Støtter avsnitt med fet/kursiv/størrelse og overskriftsstil (Heading1–3) via en liten styles.xml.
 * Bruk:
 *   $w = new DocxWriter();
 *   $w->paragraph('Tekst', ['bold' => true, 'size' => 32]); // size i halvpunkter som i docx
 *   $w->heading('Emne', 2);
 *   $bytes = $w->toBytes();
 */
class DocxWriter
{
    /** @var string[] ferdig XML for hvert avsnitt */
    private array $body = [];

    /**
     * @param array{bold?: bool, italic?: bool, size?: int, align?: string} $opts  size = halvpunkter (24 = 12pt)
     */
    public function paragraph(string $text, array $opts = []): static
    {
        $pPr = '';
        if (!empty($opts['align'])) {
            $pPr = '<w:pPr><w:jc w:val="' . $this->esc($opts['align']) . '"/></w:pPr>';
        }
        $rPr = '';
        if (!empty($opts['bold'])) {
            $rPr .= '<w:b/>';
        }
        if (!empty($opts['italic'])) {
            $rPr .= '<w:i/>';
        }
        if (!empty($opts['size'])) {
            $rPr .= '<w:sz w:val="' . (int) $opts['size'] . '"/><w:szCs w:val="' . (int) $opts['size'] . '"/>';
        }
        $run = $text === ''
            ? ''
            : '<w:r>' . ($rPr ? "<w:rPr>$rPr</w:rPr>" : '') . '<w:t xml:space="preserve">' . $this->esc($text) . '</w:t></w:r>';
        $this->body[] = "<w:p>$pPr$run</w:p>";
        return $this;
    }

    public function heading(string $text, int $level = 2): static
    {
        $level = max(1, min(3, $level));
        $this->body[] = '<w:p><w:pPr><w:pStyle w:val="Heading' . $level . '"/></w:pPr><w:r><w:t xml:space="preserve">'
            . $this->esc($text) . '</w:t></w:r></w:p>';
        return $this;
    }

    public function toBytes(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Kunne ikke opprette docx-arkiv');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRels());
        $zip->addFromString('word/document.xml', $this->document());
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function document(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . implode('', $this->body)
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1417" w:right="1417" w:bottom="1417" w:left="1417" w:header="708" w:footer="708" w:gutter="0"/>'
            . '</w:sectPr></w:body></w:document>';
    }

    private function styles(): string
    {
        $h = fn (int $lvl, int $size) => '<w:style w:type="paragraph" w:styleId="Heading' . $lvl . '">'
            . '<w:name w:val="heading ' . $lvl . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="' . ($lvl - 1) . '"/></w:pPr>'
            . '<w:rPr><w:b/><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr></w:style>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="nb-NO"/>'
            . '</w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . $h(1, 32) . $h(2, 28) . $h(3, 24)
            . '</w:styles>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private function documentRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }
}
