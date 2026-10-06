<?php

namespace App\Functions;

use App\Models\User;

/**
 * Basisklasse for funksjoner migrert fra base44/functions/<navn>/entry.ts.
 *
 * Kontrakt: __invoke(?User $user, array $payload): array
 *  - $user er innlogget bruker (null for offentlige endepunkt)
 *  - $payload er JSON-kroppen fra frontend (samme form som Deno-versjonen leste med req.json())
 *  - returverdien JSON-serialiseres og sendes tilbake uendret – behold samme feltnavn som før,
 *    slik at frontenden ikke må endres
 *  - feil: kast FunctionException('melding', 403) → {"error": "melding"} med status 403
 *
 * Base44-ekvivalenter:
 *  base44.auth.me()                         → $user
 *  user.role !== 'admin'                    → $this->requireAdmin($user)
 *  base44.asServiceRole.entities.X.create() → \App\Models\X::create([...])   (service role = ingen policy-sjekk)
 *  base44.entities.X.filter({...})          → \App\Models\X::base44Filter([...])->get()
 *  Deno.env.get('KEY')                      → config('services.<tjeneste>.<key>')  (aldri env() direkte – config caches)
 *  integrations.Core.InvokeLLM              → app(\App\Services\Llm::class)->json($prompt, $schema)
 *  integrations.Core.SendEmail              → \Mail::to($to)->send(new \App\Mail\PlainMail($subject, $body))
 */
abstract class Base44Function
{
    abstract public function __invoke(?User $user, array $payload): array;

    protected function requireUser(?User $user): User
    {
        return $user ?? throw new FunctionException('Unauthorized', 401);
    }

    protected function requireAdmin(?User $user): User
    {
        $u = $this->requireUser($user);
        if (!$u->hasRole('admin')) {
            throw new FunctionException('Forbidden', 403);
        }
        return $u;
    }

    protected function require(array $payload, string ...$keys): void
    {
        $missing = array_filter($keys, fn ($k) => !isset($payload[$k]) || $payload[$k] === '');
        if ($missing) {
            throw new FunctionException('Mangler: ' . implode(', ', $missing), 400);
        }
    }
}
