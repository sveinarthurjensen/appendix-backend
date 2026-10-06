<?php

namespace App\Functions;

use App\Models\PropertyAvailability;
use App\Models\User;
use App\Services\Ical;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/importPropertyIcal/entry.ts
 *
 * Admin importerer en iCal-feed (Airbnb o.l.) og markerer dagene som opptatt
 * i PropertyAvailability (source = airbnb_import).
 * Svar: {success: true, blocked_days, created, updated}
 */
class ImportPropertyIcal extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        // Originalen svarte 401 "Unauthorized" både for uinnlogget og ikke-admin
        if (!$user || !$user->hasRole('admin')) {
            throw new FunctionException('Unauthorized', 401);
        }

        $propertyId = $payload['property_id'] ?? null;
        $icalUrl = $payload['ical_url'] ?? null;
        if (!$propertyId || !$icalUrl) {
            throw new FunctionException('property_id og ical_url kreves', 400);
        }

        try {
            $res = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'AppendixProperties/1.0'])
                ->get($icalUrl);
        } catch (\Throwable $e) {
            throw new FunctionException($e->getMessage(), 500);
        }
        if (!$res->successful()) {
            throw new FunctionException('Kunne ikke hente iCal (HTTP ' . $res->status() . ')', 502);
        }

        $events = Ical::parse($res->body());
        $days = Ical::blockedDaysFromEvents($events);

        $existing = PropertyAvailability::base44Filter(['property_id' => $propertyId])->get();
        $byDate = [];
        foreach ($existing as $r) {
            $byDate[$this->dateStr($r->date)] = $r;
        }

        $created = 0;
        $updated = 0;
        foreach ($days as $date) {
            $ex = $byDate[$date] ?? null;
            if ($ex) {
                if ($ex->is_available !== false || $ex->source !== 'airbnb_import') {
                    $ex->update(['is_available' => false, 'source' => 'airbnb_import']);
                    $updated++;
                }
            } else {
                PropertyAvailability::create([
                    'property_id' => $propertyId,
                    'date' => $date,
                    'is_available' => false,
                    'source' => 'airbnb_import',
                ]);
                $created++;
            }
        }

        return [
            'success' => true,
            'blocked_days' => count($days),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function dateStr(mixed $d): string
    {
        return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : substr((string) $d, 0, 10);
    }
}
