<?php

namespace App\Functions;

use App\Models\Property;
use App\Models\PropertyAvailability;
use App\Models\User;
use App\Services\Ical;
use Illuminate\Http\Response;

/**
 * Portert fra base44/functions/exportPropertyIcal/entry.ts
 *
 * OFFENTLIG endepunkt (ingen innlogging): Airbnb/Booking henter opptatt-kalenderen for en
 * eiendom via ?token=<ical_export_token>&property_id=<id>. Originalen svarte med rå
 * text/calendar, ikke JSON – derfor:
 *
 *  - __invoke() returnerer {ical, filename, content_type} (JSON-kontrakten i FunctionController),
 *  - response() gir det faktiske .ics-svaret og trengs en egen, uautentisert GET-rute, f.eks. i routes/api.php:
 *      Route::get('/functions/exportPropertyIcal', fn (Request $r) => app(ExportPropertyIcal::class)->response($r->query()));
 *    (ruten ligger utenfor denne porteringen – legg den til ved siden av /health.)
 *
 * Feil svares som ren tekst med status, som i originalen: 400 "Missing params", 404 "Not found", 403 "Forbidden".
 */
class ExportPropertyIcal extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $token = $payload['token'] ?? null;
        $propertyId = $payload['property_id'] ?? null;
        if (!$token || !$propertyId) {
            throw new FunctionException('Missing params', 400);
        }

        // Service-rolle: ingen policy-sjekk, bare token-match
        $prop = Property::find($propertyId);
        if (!$prop) {
            throw new FunctionException('Not found', 404);
        }
        if ($prop->ical_export_token !== $token) {
            throw new FunctionException('Forbidden', 403);
        }

        $blocked = PropertyAvailability::base44Filter(['property_id' => $propertyId])->get()
            ->filter(fn ($r) => $r->is_available === false)
            ->map(fn ($r) => $r->date instanceof \DateTimeInterface ? $r->date->format('Y-m-d') : substr((string) $r->date, 0, 10))
            ->sort()
            ->values()
            ->all();

        $ranges = Ical::groupContiguousDates($blocked);
        $ical = Ical::build($prop->name ?: 'Opptatt', $ranges);
        $safeName = preg_replace('/[^a-z0-9]/i', '', $prop->name ?: 'opptatt');

        return [
            'ical' => $ical,
            'filename' => $safeName . '.ics',
            'content_type' => 'text/calendar; charset=utf-8',
        ];
    }

    /** Rått .ics-svar for den offentlige GET-ruten (samme headere som Deno-versjonen). */
    public function response(array $query): Response
    {
        try {
            $r = $this->__invoke(null, $query);
        } catch (FunctionException $e) {
            return new Response($e->getMessage(), $e->status, ['Content-Type' => 'text/plain; charset=utf-8']);
        } catch (\Throwable $e) {
            report($e);
            return new Response('Internal error: ' . $e->getMessage(), 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        return new Response($r['ical'], 200, [
            'Content-Type' => $r['content_type'],
            'Content-Disposition' => 'attachment; filename="' . $r['filename'] . '"',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
