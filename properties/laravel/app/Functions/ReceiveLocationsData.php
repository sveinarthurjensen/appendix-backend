<?php

namespace App\Functions;

use App\Models\Location;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portert fra base44/functions/receiveLocationsData/entry.ts
 *
 * Webhook fra andre apper (Prime Leie, Medhjelp, Appendix Holding m.fl.) som sender lokasjoner hit:
 * POST /api/functions/receiveLocationsData med «Authorization: Bearer <token>» – ingen innlogging.
 * Token: config('services.webhooks.receive_locations_token') (env RECEIVE_LOCATIONS_TOKEN, faller
 * tilbake til MASTER_SYNC_TOKEN som originalen brukte).
 *
 * Kropp: {source_app, company_id?, locations: [...], consolidated?, timestamp?}
 * Hver lokasjon finnes igjen på source_app + name og oppdateres, ellers opprettes den.
 * Svar (200): {success, source_app, company_id, timestamp, processed, created, updated, errors,
 *              results: {created, updated, errors}, consolidated_received}
 * Feil: 401 {success:false, error:'Unauthorized'}, 400 {success:false, error:'Missing required fields: …'}.
 */
class ReceiveLocationsData extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $expected = (string) (config('services.webhooks.receive_locations_token') ?? '');
        $provided = (string) ($payload['__token'] ?? request()->bearerToken() ?? '');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new FunctionException('Unauthorized', 401, ['success' => false]);
        }

        $sourceApp = $payload['source_app'] ?? null;
        $companyId = $payload['company_id'] ?? null;
        $locations = $payload['locations'] ?? null;
        $consolidated = $payload['consolidated'] ?? null;
        $timestamp = $payload['timestamp'] ?? null;

        if (!$sourceApp || !is_array($locations)) {
            throw new FunctionException('Missing required fields: source_app, locations', 400, ['success' => false]);
        }

        $results = [
            'success' => true,
            'source_app' => $sourceApp,
            'company_id' => $companyId,
            'timestamp' => $timestamp ?: now()->toIso8601String(),
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        foreach ($locations as $location) {
            $location = is_array($location) ? $location : [];
            try {
                $existing = Location::base44Filter(['source_app' => $sourceApp, 'name' => $location['name'] ?? null])->first();

                $now = now()->toIso8601String();
                $data = [
                    'source_app' => $sourceApp,
                    'company_id' => $companyId,
                    'external_id' => $location['external_id'] ?? $location['id'] ?? null,
                    'name' => $location['name'] ?? null,
                    'type' => $location['type'] ?? 'bygning',
                    'street' => $location['street'] ?? null,
                    'postal_code' => $location['postal_code'] ?? null,
                    'city' => $location['city'] ?? null,
                    'area_sqm' => $location['area_sqm'] ?? null,
                    'status' => $location['status'] ?? 'aktiv',
                    'description' => $location['description'] ?? null,
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'last_sync' => $now,
                    'sync_metadata' => [
                        'economics' => $location['economics'] ?? null,
                        'consolidated' => $consolidated ?: null,
                        'sync_timestamp' => $now,
                    ],
                ];

                if ($existing) {
                    $existing->update($data);
                    $results['updated']++;
                } else {
                    Location::create($data);
                    $results['created']++;
                }
                $results['processed']++;
            } catch (\Throwable $e) {
                $results['errors'][] = ['location' => $location['name'] ?? null, 'error' => $e->getMessage()];
            }
        }

        return $results + [
            'results' => [
                'created' => $results['created'],
                'updated' => $results['updated'],
                'errors' => $results['errors'],
            ],
            'consolidated_received' => (bool) $consolidated,
        ];
    }

    /** JSON-svar for den offentlige POST-ruten (samme feilform som originalen: {success:false, error}). */
    public function response(Request $request): JsonResponse
    {
        try {
            $payload = $request->json()->all();
            $payload['__token'] = (string) ($request->bearerToken() ?? '');
            return response()->json($this->__invoke(null, $payload));
        } catch (FunctionException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()] + $e->extra, $e->status);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
