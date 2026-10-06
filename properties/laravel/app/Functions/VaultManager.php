<?php

namespace App\Functions;

use App\Models\User;
use App\Models\VaultEntry;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Portert fra base44/functions/vaultManager/entry.ts
 *
 * Handlinger (payload.action): list | decrypt | create | update | delete.
 *
 * KRYPTERING – AVVIK FRA ORIGINALEN:
 *  Deno-versjonen brukte WebCrypto AES-256-GCM med nøkkel avledet via
 *  PBKDF2(passord='appendix-vault-master-key-v1', salt='appendix-properties-vault-salt',
 *  100000 iterasjoner, SHA-256), 12-byte tilfeldig IV, og lagret base64(ciphertext+tag)
 *  i encrypted_value/encrypted_notes og base64(iv) i iv/notes_iv.
 *  Her brukes Laravel Crypt::encryptString/decryptString (APP_KEY, AES-256-CBC + HMAC).
 *  IV ligger inne i Laravel-payloaden, så feltene iv/notes_iv settes til null for nye rader.
 *  => Eksisterende krypterte verdier fra Base44 MÅ dekrypteres med originalens nøkkel/algoritme
 *     og re-krypteres med Crypt ved import (importskript: AES-GCM-dekryptering i PHP via
 *     openssl_decrypt('aes-256-gcm') med nøkkel = hash_pbkdf2('sha256', 'appendix-vault-master-key-v1',
 *     'appendix-properties-vault-salt', 100000, 32, true), tag = siste 16 byte av ciphertext).
 */
class VaultManager extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $user = $this->requireUser($user);
        $isAdmin = $user->hasRole('admin');
        $action = $payload['action'] ?? null;

        switch ($action) {
            case 'list': {
                $q = VaultEntry::base44Filter(['is_active' => true]);
                if (!$isAdmin) {
                    $q->whereJsonContains('shared_with', $user->id);
                }
                $entries = $q->get()->map(function (VaultEntry $e) {
                    $a = $e->toBase44Array();
                    unset($a['encrypted_value'], $a['iv'], $a['encrypted_notes'], $a['notes_iv']);
                    return $a;
                })->values()->all();
                return ['entries' => $entries];
            }

            case 'decrypt': {
                $entryId = $payload['entry_id'] ?? null;
                $entry = VaultEntry::base44Filter(['id' => $entryId, 'is_active' => true])->first();
                if (!$entry) {
                    throw new FunctionException('Ikke funnet', 404);
                }
                if (!$isAdmin && !in_array($user->id, $entry->shared_with ?? [], true)) {
                    throw new FunctionException('Ingen tilgang', 403);
                }
                $value = $this->decryptText($entry->encrypted_value);
                $notes = $this->decryptText($entry->encrypted_notes);

                $entry->update([
                    'last_accessed' => now(),
                    'last_accessed_by' => $user->email,
                ]);
                return ['value' => $value, 'notes' => $notes];
            }

            case 'create': {
                if (!$isAdmin) {
                    throw new FunctionException('Kun admin kan opprette', 403);
                }
                $entry = VaultEntry::create([
                    'title' => $payload['title'] ?? null,
                    'entry_type' => $payload['entry_type'] ?? null,
                    'property_id' => $payload['property_id'] ?? null,
                    'property_name' => $payload['property_name'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'username' => $payload['username'] ?? null,
                    'url' => $payload['url'] ?? null,
                    'encrypted_value' => $this->encryptText($payload['value'] ?? null),
                    'iv' => null,
                    'encrypted_notes' => $this->encryptText($payload['notes'] ?? null),
                    'notes_iv' => null,
                    'shared_with' => $payload['shared_with'] ?? [],
                    'access_level' => $payload['access_level'] ?? 'admin',
                    'is_active' => true,
                ]);
                return ['success' => true, 'entry_id' => $entry->id];
            }

            case 'update': {
                if (!$isAdmin) {
                    throw new FunctionException('Kun admin', 403);
                }
                $entryId = $payload['entry_id'] ?? null;
                $entry = VaultEntry::find($entryId) ?? throw new FunctionException('Ikke funnet', 404);

                $updates = $payload;
                unset($updates['action'], $updates['entry_id'], $updates['value'], $updates['notes']);
                // Kun kjente felt kan oppdateres (originalen sendte resten rett videre til SDK-en).
                $updates = array_intersect_key($updates, array_flip($entry->getFillable()));

                if (array_key_exists('value', $payload)) {
                    $updates['encrypted_value'] = $this->encryptText($payload['value']);
                    $updates['iv'] = null;
                }
                if (array_key_exists('notes', $payload)) {
                    $updates['encrypted_notes'] = $this->encryptText($payload['notes']);
                    $updates['notes_iv'] = null;
                }
                $entry->update($updates);
                return ['success' => true];
            }

            case 'delete': {
                if (!$isAdmin) {
                    throw new FunctionException('Kun admin', 403);
                }
                $entry = VaultEntry::find($payload['entry_id'] ?? null) ?? throw new FunctionException('Ikke funnet', 404);
                $entry->update(['is_active' => false]);
                return ['success' => true];
            }

            default:
                throw new FunctionException('Ukjent handling', 400);
        }
    }

    private function encryptText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        return Crypt::encryptString($text);
    }

    private function decryptText(?string $ciphertext): string
    {
        if (!$ciphertext) {
            return '';
        }
        try {
            return Crypt::decryptString($ciphertext);
        } catch (DecryptException $e) {
            // Verdi som fortsatt er kryptert med Base44-nøkkelen (ikke re-kryptert ved import).
            throw new FunctionException('Kunne ikke dekryptere – verdien må re-krypteres etter import', 500);
        }
    }
}
