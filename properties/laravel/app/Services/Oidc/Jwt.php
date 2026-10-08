<?php

namespace App\Services\Oidc;

/**
 * Minimal JWT (RS256) i ren PHP – ingen composer-pakker, bare ext-openssl.
 * Erstatter signIdToken()/b64url-hjelperne i base44/shared/oidcProvider.ts, og brukes også
 * til å verifisere Microsofts id_token i EntraController (JWK → PEM via jwkToPem()).
 */
class Jwt
{
    public static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $s): string
    {
        $b64 = strtr($s, '-_', '+/');
        $pad = (4 - strlen($b64) % 4) % 4;
        return (string) base64_decode($b64 . str_repeat('=', $pad), true);
    }

    /** Tilfeldig base64url-streng (rand() i oidcProvider.ts). */
    public static function rand(int $bytes = 16): string
    {
        return self::b64url(random_bytes($bytes));
    }

    /** Signer claims med RS256. $privatePem = PKCS#8 "BEGIN PRIVATE KEY". */
    public static function sign(array $claims, string $privatePem, string $kid): string
    {
        $header = self::b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid], JSON_UNESCAPED_SLASHES));
        $payload = self::b64url(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false) {
            throw new \RuntimeException('Ugyldig privat signeringsnøkkel: ' . openssl_error_string());
        }
        $sig = '';
        if (!openssl_sign("$header.$payload", $sig, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Signering feilet: ' . openssl_error_string());
        }
        return "$header.$payload." . self::b64url($sig);
    }

    /** Header + claims uten signaturkontroll (for å finne kid før nøkkel slås opp). */
    public static function decodeUnverified(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Ugyldig JWT-format');
        }
        $header = json_decode(self::b64urlDecode($parts[0]), true);
        $claims = json_decode(self::b64urlDecode($parts[1]), true);
        if (!is_array($header) || !is_array($claims)) {
            throw new \InvalidArgumentException('Ugyldig JWT-innhold');
        }
        return ['header' => $header, 'claims' => $claims];
    }

    /**
     * Verifiser RS256-signatur og returner claims. $publicPem kan være "BEGIN PUBLIC KEY" eller et sertifikat.
     * Kaster ved feil signatur, feil alg eller utløpt token (exp/nbf med 60 s slingringsmonn).
     */
    public static function verify(string $jwt, string $publicPem, int $leeway = 60): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Ugyldig JWT-format');
        }
        $decoded = self::decodeUnverified($jwt);
        if (($decoded['header']['alg'] ?? '') !== 'RS256') {
            throw new \RuntimeException('Ustøttet alg: ' . ($decoded['header']['alg'] ?? '?'));
        }
        $key = openssl_pkey_get_public($publicPem);
        if ($key === false) {
            throw new \RuntimeException('Ugyldig offentlig nøkkel: ' . openssl_error_string());
        }
        $ok = openssl_verify("{$parts[0]}.{$parts[1]}", self::b64urlDecode($parts[2]), $key, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new \RuntimeException('JWT-signaturen er ugyldig');
        }
        $claims = $decoded['claims'];
        $now = time();
        if (isset($claims['exp']) && $now > (int) $claims['exp'] + $leeway) {
            throw new \RuntimeException('JWT er utløpt');
        }
        if (isset($claims['nbf']) && $now + $leeway < (int) $claims['nbf']) {
            throw new \RuntimeException('JWT er ikke gyldig ennå');
        }
        return $claims;
    }

    /** RSA-JWK (n, e) → PEM "BEGIN PUBLIC KEY" (SubjectPublicKeyInfo, DER bygd for hånd). */
    public static function jwkToPem(array $jwk): string
    {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            // Microsofts JWKS har også x5c – bruk sertifikatet når n/e mangler
            if (!empty($jwk['x5c'][0])) {
                return "-----BEGIN CERTIFICATE-----\n" . chunk_split($jwk['x5c'][0], 64, "\n") . "-----END CERTIFICATE-----\n";
            }
            throw new \InvalidArgumentException('JWK er ikke en RSA-nøkkel');
        }
        $n = self::derInteger(self::b64urlDecode($jwk['n']));
        $e = self::derInteger(self::b64urlDecode($jwk['e']));
        $rsaPublicKey = self::derSequence($n . $e);
        $algId = self::derSequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00"); // rsaEncryption + NULL
        $spki = self::derSequence($algId . self::derBitString($rsaPublicKey));
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derLength(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        $bytes = ltrim(pack('N', $len), "\0");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function derInteger(string $bin): string
    {
        $bin = ltrim($bin, "\0");
        if ($bin === '' || (ord($bin[0]) & 0x80)) {
            $bin = "\0" . $bin; // positivt fortegn
        }
        return "\x02" . self::derLength(strlen($bin)) . $bin;
    }

    private static function derSequence(string $bin): string
    {
        return "\x30" . self::derLength(strlen($bin)) . $bin;
    }

    private static function derBitString(string $bin): string
    {
        return "\x03" . self::derLength(strlen($bin) + 1) . "\0" . $bin;
    }
}
