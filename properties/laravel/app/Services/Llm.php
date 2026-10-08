<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Erstatter integrations.Core.InvokeLLM. Bruker Anthropic Messages API.
 * Konfig: services.anthropic.key, services.anthropic.model
 */
class Llm
{
    /**
     * Apper uten helseopplysninger – kan bruke LLM fritt.
     * Helse-apper (klinikkportal, kommuneoverlegene …) får KUN kalle med $pseudonymised = true,
     * og hvert slikt kall logges. Beslutning 8.10.2026: helsedata skal ikke til eksterne LLM-modeller.
     */
    public const APPS_WITHOUT_HEALTH_DATA = ['appendix_properties', 'appendix_holding', 'foreningsdomstolen'];

    private string $appId = 'appendix_properties';
    private ?string $caller = null;
    private bool $pseudonymised = false;

    /** Sett hvilken app/funksjon som kaller, og om dataene er pseudonymiserte (kreves for helse-apper). */
    public function from(string $appId, ?string $caller = null, bool $pseudonymised = false): static
    {
        $c = clone $this;
        $c->appId = $appId; $c->caller = $caller; $c->pseudonymised = $pseudonymised;
        return $c;
    }

    private function guard(string $prompt): void
    {
        if (in_array($this->appId, self::APPS_WITHOUT_HEALTH_DATA, true)) {
            return;
        }
        if (!$this->pseudonymised) {
            throw new \RuntimeException("LLM sperret: appen {$this->appId} behandler helseopplysninger. Kall tillates bare med pseudonymiserte data (->from(app, caller, pseudonymised: true)).");
        }
        \Illuminate\Support\Facades\Log::channel(config('logging.default'))->info('LLM-kall med pseudonymiserte data', [
            'app_id' => $this->appId, 'caller' => $this->caller, 'prompt_length' => strlen($prompt),
        ]);
    }

    public function text(string $prompt, int $maxTokens = 4000): string
    {
        $this->guard($prompt);
        $r = $this->call($prompt, $maxTokens);
        return $r['content'][0]['text'] ?? '';
    }

    /** Ber om JSON etter gitt skjema og returnerer dekodet array (tom array ved feil). */
    public function json(string $prompt, array $schema, int $maxTokens = 4000): array
    {
        $full = $prompt . "\n\nSvar KUN med gyldig JSON som følger dette skjemaet, uten forklaring og uten kodeblokk:\n" . json_encode($schema, JSON_UNESCAPED_UNICODE);
        $txt = $this->text($full, $maxTokens);
        $txt = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($txt));
        $decoded = json_decode($txt, true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $txt, $m)) {
            $decoded = json_decode($m[0], true);
        }
        return is_array($decoded) ? $decoded : [];
    }

    private function call(string $prompt, int $maxTokens): array
    {
        $key = config('services.anthropic.key') ?: throw new \RuntimeException('ANTHROPIC_API_KEY mangler');
        return Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-sonnet-4-5'),
                'max_tokens' => $maxTokens,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ])->throw()->json();
    }
}
