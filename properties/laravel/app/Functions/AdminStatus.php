<?php

namespace App\Functions;

use App\Models\Booking;
use App\Models\CaseRecord;
use App\Models\InboxMessage;
use App\Models\MaintenanceTask;
use App\Models\NettsideStatus;
use App\Models\PortalMessage;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portert fra base44/functions/adminStatus/entry.ts
 *
 * Statusoversikt for Arbeidsflaten (ekstern app): åpne innbokser + nettsider som er nede.
 * GET /api/functions/adminStatus – ingen innlogging, men krever header «x-arbeidsflate-nokkel»
 * lik config('services.webhooks.admin_status_token') (env ARBEIDSFLATE_NOKKEL). Sammenlignes i
 * konstant tid (hash_equals). Mangler nøkkelen i config → 503 {error:'service_unavailable'};
 * feil nøkkel → 401 {error:'unauthorized'}; andre feil → 500 {error:'internal_error'}.
 *
 * Svar: {app, generert, innbokser: [{id, navn, apen, nye_24t, eldste_apne, lenke}], varsler: [{niva, tekst}]}
 * Lenkene bygges fra config('services.webhooks.admin_status_base_url') (secret APP_BASE_URL).
 */
class AdminStatus extends Base44Function
{
    private const APP_NAME = 'Appendix Properties AS';

    public function __invoke(?User $user, array $payload): array
    {
        $expected = (string) (config('services.webhooks.admin_status_token') ?? '');
        if ($expected === '') {
            throw new FunctionException('service_unavailable', 503);
        }
        $provided = (string) ($payload['__nokkel'] ?? request()->header('x-arbeidsflate-nokkel', '') ?? '');
        if (!hash_equals($expected, $provided)) {
            throw new FunctionException('unauthorized', 401);
        }

        $cutoff = now()->subDay();
        $baseUrl = rtrim((string) (config('services.webhooks.admin_status_base_url') ?: 'https://aprop.no'), '/');

        $buildInbox = function (string $id, string $navn, string $model, array $query, string $page) use ($cutoff, $baseUrl): array {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
            $apen = $model::base44Filter($query)->count();
            $nye24t = $model::base44Filter($query)->where('created_date', '>=', $cutoff)->count();
            $eldste = $model::base44Filter($query)->orderBy('created_date')->value('created_date');
            return [
                'id' => $id,
                'navn' => $navn,
                'apen' => $apen,
                'nye_24t' => $nye24t,
                'eldste_apne' => $eldste ? \Carbon\Carbon::parse($eldste)->toIso8601String() : null,
                'lenke' => $baseUrl . '/' . $page,
            ];
        };

        $innbokser = [
            $buildInbox('henvendelser_nettside', 'Henvendelser fra nettsiden', CaseRecord::class,
                ['case_type' => 'henvendelse', 'status' => ['$in' => ['ny', 'under_behandling', 'avventer_svar']]], 'CaseManagement'),
            $buildInbox('reservasjoner', 'Reservasjonsforespørsler', Booking::class,
                ['status' => 'forespørsel'], 'ShortTermBookings'),
            $buildInbox('invitasjoner', 'Invitasjoner som venter', UserInvite::class,
                ['status' => ['$in' => ['draft', 'sent']]], 'UserInvites'),
            $buildInbox('innboks', 'Innboks', InboxMessage::class,
                ['status' => 'ny', 'direction' => 'incoming'], 'CommunicationCenter'),
            $buildInbox('svar_minside', 'Svar fra Min side', PortalMessage::class,
                ['direction' => 'innkommende', 'status' => 'ulest'], 'CaseManagement'),
            $buildInbox('vedlikehold', 'Vedlikeholdsoppgaver (nye)', MaintenanceTask::class,
                ['status' => 'ny'], 'MaintenanceCenter'),
        ];

        // NettsideStatus: én «kritisk» per side som er nede (navn + url + nede siden)
        $nedesider = NettsideStatus::base44Filter(['ok' => false])->orderBy('created_date')->limit(100)->get();
        $varsler = $nedesider->map(function ($s) {
            $nedeSiden = $s->nede_siden instanceof \DateTimeInterface ? $s->nede_siden->toIso8601String() : (string) ($s->nede_siden ?? '');
            return [
                'niva' => 'kritisk',
                'tekst' => trim(($s->navn ?: 'Nettside') . ' ' . ($s->url ?: '') . ($nedeSiden !== '' ? ' nede siden ' . $nedeSiden : '')),
            ];
        })->values()->all();

        return [
            'app' => self::APP_NAME,
            'generert' => now()->toIso8601String(),
            'innbokser' => $innbokser,
            'varsler' => $varsler,
        ];
    }

    /** JSON-svar for den offentlige GET-ruten (Cache-Control: no-store som originalen). */
    public function response(Request $request): JsonResponse
    {
        $headers = ['Cache-Control' => 'no-store'];
        try {
            $payload = ['__nokkel' => (string) $request->header('x-arbeidsflate-nokkel', '')];
            return response()->json($this->__invoke(null, $payload), 200, $headers);
        } catch (FunctionException $e) {
            return response()->json(['error' => $e->getMessage()] + $e->extra, $e->status, $headers);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'internal_error'], 500, $headers);
        }
    }
}
