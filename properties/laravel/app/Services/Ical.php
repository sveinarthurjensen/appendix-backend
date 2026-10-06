<?php

namespace App\Services;

/**
 * Portert fra base44/shared/ical.ts
 *
 * Minimal iCal (RFC 5545)-hjelper: parsing ved import og bygging ved eksport.
 * Brukes av ImportPropertyIcal og ExportPropertyIcal. Alle datoer behandles i UTC,
 * nøyaktig som Deno-versjonen (Date.UTC).
 */
class Ical
{
    /** "20260115" / "20260115T120000Z" → unix-tidspunkt (UTC), null om tomt */
    private static function toTimestamp(?string $val, bool $isDateParam): ?int
    {
        $raw = trim((string) $val);
        if ($raw === '') {
            return null;
        }
        $isDate = $isDateParam || (strlen($raw) >= 8 && ($raw[8] ?? '') !== 'T');
        $y = (int) substr($raw, 0, 4);
        $m = (int) substr($raw, 4, 2);
        $d = (int) substr($raw, 6, 2);
        if ($isDate) {
            return gmmktime(0, 0, 0, $m, $d, $y);
        }
        $h = (int) (substr($raw, 9, 2) ?: 0);
        $mi = (int) (substr($raw, 11, 2) ?: 0);
        $s = (int) (substr($raw, 13, 2) ?: 0);
        return gmmktime($h, $mi, $s, $m, $d, $y);
    }

    /**
     * @return array<int, array{start: ?int, end: ?int, summary: ?string, uid: ?string}>
     *         start/end er unix-tidspunkt (UTC)
     */
    public static function parse(?string $text): array
    {
        $unfolded = preg_replace("/\r?\n[ \t]/", '', (string) $text);
        $lines = preg_split("/\r?\n/", $unfolded);
        $events = [];
        $inEvent = false;
        $cur = null;
        foreach ($lines as $line) {
            $upper = strtoupper($line);
            if ($upper === 'BEGIN:VEVENT') {
                $inEvent = true;
                $cur = [];
                continue;
            }
            if ($upper === 'END:VEVENT') {
                if ($cur && !empty($cur['dtstart'])) {
                    $events[] = $cur;
                }
                $inEvent = false;
                $cur = null;
                continue;
            }
            if (!$inEvent || $cur === null) {
                continue;
            }
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $propPart = substr($line, 0, $colon);
            $val = substr($line, $colon + 1);
            $semi = strpos($propPart, ';');
            $name = strtoupper($semi !== false ? substr($propPart, 0, $semi) : $propPart);
            $params = $semi !== false ? strtoupper(substr($propPart, $semi + 1)) : '';
            if ($name === 'DTSTART') {
                $cur['dtstart'] = self::toTimestamp($val, str_contains($params, 'VALUE=DATE'));
            } elseif ($name === 'DTEND') {
                $cur['dtend'] = self::toTimestamp($val, str_contains($params, 'VALUE=DATE'));
            } elseif ($name === 'SUMMARY') {
                $cur['summary'] = $val;
            } elseif ($name === 'UID') {
                $cur['uid'] = $val;
            }
        }
        return array_map(fn ($e) => [
            'start' => $e['dtstart'],
            'end' => $e['dtend'] ?? $e['dtstart'],
            'summary' => $e['summary'] ?? null,
            'uid' => $e['uid'] ?? null,
        ], $events);
    }

    /** Alle datoer (Y-m-d) som dekkes av hendelsene; DTEND er eksklusiv. */
    public static function blockedDaysFromEvents(array $events): array
    {
        $days = [];
        foreach ($events as $e) {
            if (empty($e['start'])) {
                continue;
            }
            $start = self::startOfDay($e['start']);
            $end = !empty($e['end']) ? self::startOfDay($e['end']) : $start + 86400;
            if ($end <= $start) {
                $end = $start + 86400;
            }
            for ($t = $start; $t < $end; $t += 86400) {
                $days[gmdate('Y-m-d', $t)] = true;
            }
        }
        return array_keys($days);
    }

    private static function startOfDay(int $ts): int
    {
        return gmmktime(0, 0, 0, (int) gmdate('n', $ts), (int) gmdate('j', $ts), (int) gmdate('Y', $ts));
    }

    private static function addDay(string $s): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $s));
        return gmdate('Y-m-d', gmmktime(0, 0, 0, $m, $d, $y) + 86400);
    }

    /**
     * Sorterte datoer → [{start, end}] der end er eksklusiv (dagen etter siste).
     * @param string[] $sortedDates
     * @return array<int, array{start: string, end: string}>
     */
    public static function groupContiguousDates(array $sortedDates): array
    {
        $ranges = [];
        foreach ($sortedDates as $d) {
            $lastIdx = count($ranges) - 1;
            // AVVIK: Deno-versjonen sammenlignet d mot addDay(last.end) (end er allerede eksklusiv),
            // og slo dermed aldri sammen påfølgende dager, men bygde bro over én ledig dag.
            // Her slås faktisk sammenhengende dager sammen, som navnet sier.
            if ($lastIdx >= 0 && $d === $ranges[$lastIdx]['end']) {
                $ranges[$lastIdx]['end'] = self::addDay($d);
            } else {
                $ranges[] = ['start' => $d, 'end' => self::addDay($d)];
            }
        }
        return $ranges;
    }

    public static function build(?string $calName, array $ranges): string
    {
        $now = gmdate('Ymd\THis\Z');
        $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Appendix Properties//Availability//NO\r\nCALSCALE:GREGORIAN\r\n";
        $out .= 'X-WR-CALNAME:' . ($calName ?: 'Opptatt') . "\r\n";
        foreach ($ranges as $i => $r) {
            $out .= "BEGIN:VEVENT\r\n";
            $out .= 'UID:avail-' . ($i + 1) . '-' . $r['start'] . "@appendix-properties.no\r\n";
            $out .= 'DTSTAMP:' . $now . "\r\n";
            $out .= 'DTSTART;VALUE=DATE:' . str_replace('-', '', $r['start']) . "\r\n";
            $out .= 'DTEND;VALUE=DATE:' . str_replace('-', '', $r['end']) . "\r\n";
            $out .= "SUMMARY:Opptatt (Appendix Properties)\r\n";
            $out .= "END:VEVENT\r\n";
        }
        $out .= "END:VCALENDAR\r\n";
        return $out;
    }
}
