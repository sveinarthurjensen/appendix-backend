<?php

namespace App\Functions;

use App\Models\Contract;
use App\Models\ParkingSpot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Portert fra base44/functions/offentligeUtleieobjekter/entry.ts
 *
 * OFFENTLIG GET-endepunkt (ingen innlogging) for nettsiden aprop.no (WordPress på egen server):
 * utleieobjektene slik forsiden (PublicProperties) viser dem. Bare det forsiden allerede viser
 * offentlig: navn, sted, type, bilde, størrelse, pris, kartposisjon og om objektet er utleid.
 * Ingen leietakere, ingen kontrakter, ingen gateadresser utover det objektet selv oppgir som sted.
 *
 * GET /api/functions/offentligeUtleieobjekter  (+ OPTIONS). WordPress mellomlagrer svaret i ti minutter;
 * vi svarer med Cache-Control: public, max-age=300 og Access-Control-Allow-Origin: * som originalen.
 *
 * Svar: {oppdatert: ISO-tid, objekter: [...]} – sortert korttid, langtid, parkering.
 */
class OffentligeUtleieobjekter extends Base44Function
{
    private const SVAR_HEADERS = [
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control' => 'public, max-age=300',
    ];

    public function __invoke(?User $user, array $payload): array
    {
        try {
            $eiendommer = Property::base44Filter(['status' => 'aktiv'])->get();
        } catch (\Throwable) {
            $eiendommer = collect();
        }
        try {
            $plasser = ParkingSpot::base44Filter(['status' => 'ledig'])->get();
        } catch (\Throwable) {
            $plasser = collect();
        }
        try {
            $kontrakter = Contract::base44Filter(['status' => 'aktiv'])->get();
        } catch (\Throwable) {
            $kontrakter = collect();
        }
        $utleid = $kontrakter->pluck('property_id')->filter()->flip();

        $objekter = $eiendommer->map(function ($p) use ($utleid) {
            $korttid = $p->rental_mode === 'korttid';
            // price: price_per_month ?? (korttid ? price_per_night : price_per_month) ?? null
            $price = $p->price_per_month ?? ($korttid ? $p->price_per_night : $p->price_per_month) ?? null;
            return [
                'kind' => $p->rental_mode,
                'id' => $p->id,
                'name' => $p->name,
                'city' => $p->city ?? '',
                'type' => $p->type ?? '',
                'main_image' => $p->main_image ?? '',
                'bedrooms' => $p->bedrooms,
                'bathrooms' => $p->bathrooms,
                'max_guests' => $p->max_guests,
                'size_sqm' => $p->size_sqm,
                'price' => $price,
                'priceUnit' => $p->price_per_month ? '/mnd' : ($korttid ? '/natt' : '/mnd'),
                'lat' => $p->latitude,
                'lng' => $p->longitude,
                'isRented' => $utleid->has($p->id),
                'detail' => 'PublicPropertyDetails?id=' . $p->id,
            ];
        });

        $parkering = $plasser->map(fn ($s) => [
            'kind' => 'parkering',
            'id' => $s->id,
            'name' => $s->name,
            'spotNumber' => $s->spot_number ?? '',
            'city' => $s->location_description ?? '',
            'main_image' => $s->image ?? '',
            'spotType' => $s->spot_type ?? '',
            'hasCharging' => (bool) $s->has_charging,
            'hasRoof' => (bool) $s->has_roof,
            'price' => $s->price_per_month ?? $s->price_per_day ?? null,
            'priceUnit' => $s->price_per_month ? '/mnd' : ($s->price_per_day ? '/dag' : ''),
            'lat' => $s->latitude,
            'lng' => $s->longitude,
            'isRented' => false,
            'detail' => 'PublicParkingDetail?id=' . $s->id,
        ]);

        $rekkefolge = ['korttid' => 0, 'langtid' => 1, 'parkering' => 2];
        $alle = $objekter->concat($parkering)
            ->sortBy(fn ($o) => $rekkefolge[$o['kind']] ?? 9, SORT_NUMERIC) // stabil sortering som JS sort
            ->values()
            ->all();

        return ['oppdatert' => now()->toIso8601String(), 'objekter' => $alle];
    }

    /** JSON-svar for den offentlige GET/OPTIONS-ruten (CORS + cache-headere som originalen). */
    public function response(Request $request): Response|JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return new Response(null, 204, self::SVAR_HEADERS + ['Access-Control-Allow-Methods' => 'GET']);
        }
        try {
            return response()->json($this->__invoke(null, []), 200, self::SVAR_HEADERS);
        } catch (FunctionException $e) {
            return response()->json(['error' => $e->getMessage()] + $e->extra, $e->status, self::SVAR_HEADERS);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Kunne ikke hente utleieobjekter'], 500, self::SVAR_HEADERS);
        }
    }
}
