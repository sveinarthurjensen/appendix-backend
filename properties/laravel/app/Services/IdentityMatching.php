<?php

namespace App\Services;

use App\Models\User;

/**
 * Portert fra base44/shared/identityMatching.ts
 *
 * Identitetsmatching for BankID-først-invitasjonsflyten.
 * Hashing er usaltet SHA-256 hex av normalisert norsk fødselsnummer (samme som
 * OIDC-broen), slik at begge systemer gir samme hash. NIN_HASH_SALT kan innføres
 * senere (krever re-hashing av alle lagrede id-er).
 */
class IdentityMatching
{
    public static function normalizeNationalId(?string $v): string
    {
        return preg_replace('/[^0-9]/', '', preg_replace('/\s+/', '', (string) $v)) ?? '';
    }

    public static function hashNin(?string $nin): string
    {
        $n = self::normalizeNationalId($nin);
        return $n === '' ? '' : hash('sha256', $n);
    }

    public static function nationalIdLast4(?string $nin): string
    {
        $n = self::normalizeNationalId($nin);
        return strlen($n) >= 4 ? substr($n, -4) : '';
    }

    /** Trekk ut 11-sifret norsk fødselsnummer fra OIDC/id_token-claims. */
    public static function extractNationalId(?array $claims): ?string
    {
        if (!$claims) {
            return null;
        }
        $keys = [
            'nin', 'national_id', 'national_id_number',
            'https://signicat.com/claims/national-id', 'https://signicat.com/claims/nin',
            'fnr', 'birthnumber', 'no_national_id', 'ssn',
        ];
        foreach ($keys as $k) {
            $v = $claims[$k] ?? null;
            if (is_string($v)) {
                $n = self::normalizeNationalId($v);
                if (preg_match('/^\d{11}$/', $n)) {
                    return $n;
                }
            }
        }
        $sub = $claims['sub'] ?? null;
        if (is_string($sub)) {
            $n = self::normalizeNationalId($sub);
            if (preg_match('/^\d{11}$/', $n)) {
                return $n;
            }
        }
        return null;
    }

    /** Match registrert User på nasjonalt id-hash (national_id_hash, fallback legacy nin_hash). */
    public static function matchUserByNinHash(string $hash): ?User
    {
        if ($hash === '') {
            return null;
        }
        try {
            return User::where('national_id_hash', $hash)->first()
                ?? User::where('nin_hash', $hash)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function matchUserByNin(?string $nin): ?User
    {
        return self::matchUserByNinHash(self::hashNin($nin));
    }
}
