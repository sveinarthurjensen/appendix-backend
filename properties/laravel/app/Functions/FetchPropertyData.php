<?php

namespace App\Functions;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/fetchPropertyData/entry.ts
 *
 * Slår opp adresse hos Kartverket (ws.geonorge.no) og returnerer adresse, koordinater
 * og alle matrikkelenheter på adressen.
 * Svar: {success: true, data: {...}} eller {success: false, message} (200) ved ingen treff.
 */
class FetchPropertyData extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $this->requireUser($user);

        $address = $payload['address'] ?? null;
        $postalCode = $payload['postalCode'] ?? '';
        $municipality = $payload['municipality'] ?? '';

        if (!$address) {
            throw new FunctionException('Adresse er påkrevd', 400);
        }

        try {
            $addressData = Http::timeout(20)
                ->get('https://ws.geonorge.no/adresser/v1/sok', [
                    'sok' => "$address $postalCode $municipality",
                    'fuzzy' => 'true',
                    'treffPerSide' => 20,
                ])->json();
        } catch (\Throwable $e) {
            throw new FunctionException($e->getMessage(), 500, ['success' => false]);
        }

        $adresser = $addressData['adresser'] ?? [];
        if (empty($adresser)) {
            return ['success' => false, 'message' => 'Fant ingen treff på adressen'];
        }

        $primary = $adresser[0];

        // Finn alle unike matrikkelenheter for denne adressen
        $matrikkelUnits = [];
        $seen = [];
        foreach ($adresser as $addr) {
            $same = ($addr['adressetekst'] ?? null) === ($primary['adressetekst'] ?? null)
                || (($addr['adressenavn'] ?? null) === ($primary['adressenavn'] ?? null)
                    && ($addr['nummer'] ?? null) === ($primary['nummer'] ?? null));
            if (!$same) {
                continue;
            }
            $key = $addr['matrikkelnummertekst'] ?? (($addr['gardsnummer'] ?? '') . '-' . ($addr['bruksnummer'] ?? ''));
            if (!isset($seen[$key]) && !empty($addr['gardsnummer'])) {
                $seen[$key] = true;
                $matrikkelUnits[] = [
                    'gnr_bnr' => $addr['matrikkelnummertekst'] ?? null,
                    'gardsnummer' => $addr['gardsnummer'] ?? null,
                    'bruksnummer' => $addr['bruksnummer'] ?? null,
                    'festenummer' => $addr['festenummer'] ?? null,
                    'seksjonsnummer' => $addr['undernummer'] ?? null,
                    'label' => $addr['matrikkelnummertekst']
                        ?? (($addr['gardsnummer'] ?? '') . '/' . ($addr['bruksnummer'] ?? '')
                            . (!empty($addr['festenummer']) ? '/' . $addr['festenummer'] : '')),
                ];
            }
        }

        return [
            'success' => true,
            'data' => [
                'address' => $primary['adressetekst'] ?? null,
                'postal_code' => $primary['postnummer'] ?? null,
                'city' => $primary['poststed'] ?? null,
                'municipality' => $primary['kommunenavn'] ?? null,
                'municipality_number' => $primary['kommunenummer'] ?? null,

                'latitude' => $primary['representasjonspunkt']['lat'] ?? null,
                'longitude' => $primary['representasjonspunkt']['lon'] ?? null,

                'gnr_bnr' => $primary['matrikkelnummertekst'] ?? null,
                'gardsnummer' => $primary['gardsnummer'] ?? null,
                'bruksnummer' => $primary['bruksnummer'] ?? null,
                'festenummer' => $primary['festenummer'] ?? null,

                'matrikkel_units' => $matrikkelUnits,
                'has_multiple_units' => count($matrikkelUnits) > 1,

                'adressetype' => $primary['objtype'] ?? null,
            ],
        ];
    }
}
