<?php

namespace App\Services\Oidc;

use App\Models\OidcSigningKey;
use Illuminate\Support\Facades\Log;

/**
 * RSA-signeringsnøkler (RS256) for OIDC-utstederen, lagret i oidc_signing_keys (modellen OidcSigningKey –
 * samme felt som Base44-entiteten: kid, private_pem, public_jwk, active, created_at).
 *
 * Portert fra ensureSigningKey()/getJwks() i base44/shared/oidcProvider.ts, med rotasjon i tillegg:
 *  - signingKey(): nyeste aktive nøkkel signerer; finnes ingen, genereres en (RSA-2048) ved første bruk
 *  - jwks(): ALLE aktive nøkler publiseres, så id_tokens signert før en rotasjon fortsatt kan verifiseres
 *  - rotate(): ny nøkkel blir signerende; gamle beholdes aktive til retire() kalles (etter maks token-levetid)
 */
class KeyStore
{
    /** Samme prefiks som Base44 (SIGNING_KID = "aprop-rs256-1"); nye nøkler får løpenummer. */
    public const KID_PREFIX = 'aprop-rs256-';

    private ?OidcSigningKey $cached = null;

    /** Nøkkelen som skal signere nå (nyeste aktive). */
    public function signingKey(): OidcSigningKey
    {
        if ($this->cached) {
            return $this->cached;
        }
        $key = OidcSigningKey::where('active', true)->orderByDesc('created_at')->orderByDesc('created_date')->first();
        if (!$key) {
            $key = $this->generate();
            Log::info('oidc: genererte første signeringsnøkkel ' . $key->kid);
        }
        return $this->cached = $key;
    }

    /** JWKS-dokument: offentlige nøkler for alle aktive nøkler (nyeste først). */
    public function jwks(): array
    {
        $this->signingKey(); // sikrer at minst én finnes
        $keys = OidcSigningKey::where('active', true)->orderByDesc('created_at')->get()
            ->map(fn (OidcSigningKey $k) => $k->public_jwk)
            ->filter()
            ->values()
            ->all();
        return ['keys' => $keys];
    }

    /** Rotasjon: lag ny nøkkel som blir signerende. Gamle forblir i JWKS til retire(). */
    public function rotate(): OidcSigningKey
    {
        $this->cached = null;
        return $this->generate();
    }

    /** Deaktiver nøkler eldre enn $days dager som ikke lenger er signerende (fjernes fra JWKS). */
    public function retire(int $days = 30): int
    {
        $current = $this->signingKey();
        return OidcSigningKey::where('active', true)
            ->where('id', '!=', $current->id)
            ->where('created_at', '<', now()->subDays($days))
            ->update(['active' => false]);
    }

    private function generate(): OidcSigningKey
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        if ($res === false) {
            throw new \RuntimeException('Kunne ikke generere RSA-nøkkel: ' . openssl_error_string());
        }
        $privatePem = '';
        if (!openssl_pkey_export($res, $privatePem)) {
            throw new \RuntimeException('Kunne ikke eksportere privat nøkkel: ' . openssl_error_string());
        }
        $details = openssl_pkey_get_details($res);
        if (!$details || empty($details['rsa']['n'])) {
            throw new \RuntimeException('Kunne ikke lese RSA-parametre');
        }

        $kid = $this->nextKid();
        $publicJwk = [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => Jwt::b64url($details['rsa']['n']),
            'e' => Jwt::b64url($details['rsa']['e']),
        ];

        return OidcSigningKey::create([
            'kid' => $kid,
            'private_pem' => $privatePem,
            'public_jwk' => $publicJwk,
            'active' => true,
            'created_at' => now(),
        ]);
    }

    /** aprop-rs256-1, aprop-rs256-2, … (også inaktive teller, så kid aldri gjenbrukes). */
    private function nextKid(): string
    {
        $max = 0;
        foreach (OidcSigningKey::withTrashed()->pluck('kid') as $kid) {
            if (preg_match('/^' . preg_quote(self::KID_PREFIX, '/') . '(\d+)$/', (string) $kid, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return self::KID_PREFIX . ($max + 1);
    }
}
