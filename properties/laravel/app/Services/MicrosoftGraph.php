<?php

namespace App\Services;

use App\Functions\FunctionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Graph-klient – erstatter Base44-connectoren «outlook»
 * (base44.asServiceRole.connectors.getConnection('outlook') → accessToken).
 *
 * Base44-connectoren brukte delegert OAuth (brukerens eget samtykke, scopes
 * Mail.ReadWrite / Mail.Send / Mail.ReadWrite.Shared). Her brukes i stedet
 * client credentials (app-only) mot tenant-en, og postbokser adresseres som
 * /users/{mailbox}/… .
 *
 * KREVER i Entra ID (app-registreringen i services.graph):
 *   API permissions → Microsoft Graph → Application permissions:
 *     - Mail.ReadWrite   (list/get/markRead/delete + lagring i Sendte elementer)
 *     - Mail.Send        (sendMail / reply)
 *   …med «Grant admin consent». Anbefalt i tillegg: en Exchange
 *   ApplicationAccessPolicy som begrenser appen til de aktuelle postboksene
 *   (post@, booking@, faktura@aprop.no).
 *
 * Konfig: services.graph.tenant / client_id / client_secret
 *         (AZURE_TENANT_ID / AZURE_CLIENT_ID / AZURE_CLIENT_SECRET).
 */
class MicrosoftGraph
{
    public const BASE = 'https://graph.microsoft.com/v1.0';
    private const SCOPE = 'https://graph.microsoft.com/.default';
    private const TIMEOUT = 30;

    public function get(string $path, array $headers = []): mixed
    {
        return $this->request('GET', $path, null, $headers);
    }

    public function post(string $path, array|string|null $body = null, array $headers = []): mixed
    {
        return $this->request('POST', $path, $body, $headers);
    }

    public function patch(string $path, array|string|null $body = null, array $headers = []): mixed
    {
        return $this->request('PATCH', $path, $body, $headers);
    }

    public function delete(string $path, array $headers = []): mixed
    {
        return $this->request('DELETE', $path, null, $headers);
    }

    /**
     * Samme kontrakt som graph()-hjelperen i Deno-versjonen: returnerer dekodet JSON
     * (null ved tom kropp) og kaster ved ikke-2xx med «Graph {status}: {tekst}».
     */
    public function request(string $method, string $path, array|string|null $body = null, array $headers = []): mixed
    {
        $url = str_starts_with($path, 'http') ? $path : self::BASE . $path;

        $req = Http::withToken($this->token())
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->withHeaders($headers);

        $res = match (strtoupper($method)) {
            'GET' => $req->get($url),
            'DELETE' => $req->delete($url),
            default => is_string($body)
                ? $req->withBody($body, 'application/json')->send(strtoupper($method), $url)
                : $req->asJson()->send(strtoupper($method), $url, ['json' => $body ?? []]),
        };

        return $this->handle($res, $method, $path);
    }

    private function handle(Response $res, string $method, string $path): mixed
    {
        $text = $res->body();
        if (!$res->successful()) {
            Log::error("[outlookMailbox] Graph {$res->status()} " . strtoupper($method) . " {$path}", ['body' => $text]);
            throw new FunctionException("Graph {$res->status()}: {$text}", 500);
        }
        if ($text === '' || $text === null) {
            return null;
        }
        $decoded = json_decode($text, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $text;
    }

    /** Client-credentials-token, cachet i Laravel Cache til (nesten) utløp. */
    public function token(): string
    {
        $tenant = config('services.graph.tenant');
        $clientId = config('services.graph.client_id');
        $secret = config('services.graph.client_secret');
        if (!$tenant || !$clientId || !$secret) {
            throw new FunctionException('Microsoft Graph ikke konfigurert (AZURE_TENANT_ID / AZURE_CLIENT_ID / AZURE_CLIENT_SECRET)', 500);
        }

        $key = 'msgraph:token:' . md5($tenant . '|' . $clientId . '|' . self::SCOPE);
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $res = Http::asForm()->timeout(self::TIMEOUT)->post(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $secret,
                'scope' => self::SCOPE,
            ],
        );

        if (!$res->successful()) {
            Log::error('[outlookMailbox] Graph token-feil ' . $res->status(), ['body' => $res->body()]);
            throw new FunctionException('Graph token ' . $res->status() . ': ' . $res->body(), 500);
        }

        $json = $res->json();
        $token = $json['access_token'] ?? null;
        if (!$token) {
            throw new FunctionException('Graph token: mangler access_token i svaret', 500);
        }

        // Trekk fra en margin så vi aldri bruker et token som er i ferd med å utløpe.
        $ttl = max(60, (int) ($json['expires_in'] ?? 3600) - 300);
        Cache::put($key, $token, $ttl);

        return $token;
    }
}
