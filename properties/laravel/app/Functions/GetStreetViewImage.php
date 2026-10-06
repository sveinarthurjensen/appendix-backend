<?php

namespace App\Functions;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/getStreetViewImage/entry.ts
 *
 * Sjekker Street View-metadata for koordinater/adresse og returnerer bilde-URL.
 * Svar: {success, available, image_url, pano_id, location} eller {success: false, available: false, message}.
 * Konfig: services.google_maps.key
 */
class GetStreetViewImage extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $this->requireUser($user);

        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        $address = $payload['address'] ?? null;

        $apiKey = config('services.google_maps.key');
        if (!$apiKey) {
            throw new FunctionException('Google Maps API-nøkkel mangler', 500, ['success' => false]);
        }

        if ($latitude && $longitude) {
            $location = "$latitude,$longitude";
        } elseif ($address) {
            $location = rawurlencode($address);
        } else {
            throw new FunctionException('Adresse eller koordinater kreves', 400, ['success' => false]);
        }

        try {
            $metadata = Http::timeout(15)
                ->get("https://maps.googleapis.com/maps/api/streetview/metadata?location=$location&key=$apiKey")
                ->json();
        } catch (\Throwable $e) {
            throw new FunctionException($e->getMessage(), 500, ['success' => false]);
        }

        if (($metadata['status'] ?? null) !== 'OK') {
            return [
                'success' => false,
                'available' => false,
                'message' => 'Street View ikke tilgjengelig for denne adressen',
            ];
        }

        // 640x480 er maks for gratis tier. Nøkkelen ligger i URL-en, som i originalen.
        $imageUrl = "https://maps.googleapis.com/maps/api/streetview?size=640x480&location=$location&fov=90&heading=0&pitch=0&key=$apiKey";

        return [
            'success' => true,
            'available' => true,
            'image_url' => $imageUrl,
            'pano_id' => $metadata['pano_id'] ?? null,
            'location' => $metadata['location'] ?? null,
        ];
    }
}
